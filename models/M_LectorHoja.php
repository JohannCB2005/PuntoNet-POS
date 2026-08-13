<?php
/**
 * Lector de hojas de cálculo SIN dependencias externas (nada de Composer/PhpSpreadsheet
 * — InfinityFree no tiene privilegios para vendor/ pesados y esto evita el problema).
 * Soporta .xlsx (ZipArchive + SimpleXML) y .csv.
 *
 * Contrato: leer() siempre devuelve STRINGS. Convertir a float aquí rompería números
 * como "0008425" (número de boleta) que dependen de conservar los ceros a la izquierda;
 * la conversión a tipo la hace cada importador, que sabe qué significa cada columna.
 */
class M_LectorHoja {

    /**
     * Lista las hojas de un .xlsx en el ORDEN del workbook (no el de sheet1.xml,
     * sheet2.xml... que puede no coincidir). Para .csv devuelve un nombre fijo.
     *
     * @return string[] Nombres de hoja visibles para el usuario
     */
    public static function hojas(string $ruta): array {
        if (self::esCsv($ruta)) {
            return ['CSV'];
        }
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new Exception('No se pudo abrir el archivo .xlsx (¿está corrupto o no es un .xlsx real?).');
        }
        $mapa = self::mapaHojas($zip);
        $zip->close();
        return array_keys($mapa);
    }

    /**
     * Lee una hoja completa (o la primera si no se especifica) y devuelve un array
     * de filas; cada fila es un array indexado 0..N-1 de strings.
     */
    public static function leer(string $ruta, ?string $hoja = null, ?int $limite = null): array {
        if (self::esCsv($ruta)) {
            return self::leerCsv($ruta, $limite);
        }
        return self::leerXlsx($ruta, $hoja, $limite);
    }

    private static function esCsv(string $ruta): bool {
        return strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'csv';
    }

    // ============================== CSV ======================================

    private static function leerCsv(string $ruta, ?int $limite): array {
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new Exception('No se pudo leer el archivo .csv.');
        }

        // BOM UTF-8 (Excel lo agrega al exportar) y detección de Windows-1252
        // (exports de sistemas web en Windows vienen así muy seguido; sin esto
        // "Pensión" llega como "Pensi?n" y el filtro por concepto falla en silencio).
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $convertido = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $contenido);
            if ($convertido !== false) {
                $contenido = $convertido;
            }
        }

        $delimitador = (substr_count($contenido, ';') > substr_count($contenido, ',')) ? ';' : ',';

        $filas = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contenido);
        rewind($handle);
        while (($fila = fgetcsv($handle, 0, $delimitador)) !== false) {
            $filas[] = array_map(fn($v) => trim((string) $v), $fila);
            if ($limite !== null && count($filas) >= $limite) {
                break;
            }
        }
        fclose($handle);
        return $filas;
    }

    // ============================== XLSX =====================================

    private static function leerXlsx(string $ruta, ?string $hoja, ?int $limite): array {
        if (!extension_loaded('zip')) {
            throw new Exception('La extensión ZipArchive de PHP no está disponible en este servidor.');
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new Exception('No se pudo abrir el archivo .xlsx (¿está corrupto o no es un .xlsx real?).');
        }

        $mapaHojas = self::mapaHojas($zip);
        if (empty($mapaHojas)) {
            $zip->close();
            throw new Exception('El archivo .xlsx no contiene hojas legibles.');
        }

        $rutaHoja = $hoja !== null && isset($mapaHojas[$hoja])
            ? $mapaHojas[$hoja]
            : reset($mapaHojas); // primera hoja del workbook, no la primera del zip

        $sst = self::sharedStrings($zip);
        $estilos = self::estilos($zip);
        $filas = self::filas($zip, $rutaHoja, $sst, $estilos, $limite);

        $zip->close();
        return $filas;
    }

    /**
     * Resuelve xl/workbook.xml contra xl/_rels/workbook.xml.rels para mapear
     * nombre de hoja (visible al usuario) -> ruta del XML dentro del zip, en el
     * ORDEN declarado en el workbook.
     *
     * El r:id de <sheet> vive en el namespace de relationships, no en el por
     * defecto: (string)$s['id'] devuelve vacío, hay que pedir el atributo con
     * $s->attributes($ns['r']). Es el error clásico que hace creer que el
     * workbook "no tiene hojas".
     */
    private static function mapaHojas(ZipArchive $zip): array {
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wbXml === false || $relsXml === false) {
            return [];
        }

        $wb = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($relsXml);
        if ($wb === false || $rels === false) {
            return [];
        }

        $porId = [];
        foreach ($rels->Relationship as $r) {
            $tipo = (string) $r['Type'];
            if (substr($tipo, -9) !== 'worksheet') {
                continue;
            }
            $destino = ltrim((string) $r['Target'], '/');
            if (strpos($destino, 'xl/') !== 0) {
                $destino = 'xl/' . $destino;
            }
            $porId[(string) $r['Id']] = $destino;
        }

        $ns = $wb->getNamespaces(true);
        $hojas = [];
        if (isset($wb->sheets->sheet)) {
            foreach ($wb->sheets->sheet as $s) {
                $attrR = isset($ns['r']) ? $s->attributes($ns['r']) : null;
                $rid = $attrR !== null ? (string) $attrR['id'] : '';
                if ($rid !== '' && isset($porId[$rid])) {
                    $hojas[(string) $s['name']] = $porId[$rid];
                }
            }
        }
        return $hojas;
    }

    /**
     * xl/sharedStrings.xml es OPCIONAL: LibreOffice y openpyxl escriben las
     * celdas de texto como t="inlineStr" y ni siquiera generan este archivo.
     * Excel al "Guardar como" sí lo produce con t="s". Ambos modos deben
     * funcionar sin fatal.
     */
    private static function sharedStrings(ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $sst = simplexml_load_string($xml);
        if ($sst === false) {
            return [];
        }
        $out = [];
        foreach ($sst->si as $si) {
            // Rich text parte el texto en varios <r><t>; (string)$si->t se comería
            // media palabra. Concatenar TODOS los <t> descendientes, con o sin runs.
            $txt = '';
            foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                $txt .= (string) $t;
            }
            $out[] = $txt;
        }
        return $out;
    }

    /**
     * Resuelve cellXfs -> numFmtId -> formatCode para poder distinguir una fecha
     * (serie numérica de Excel) de un monto (también numérico). Sin esto,
     * FECHA_PAGO sale como "46238" en vez de "2026-08-04".
     */
    private static function estilos(ZipArchive $zip): array {
        $xml = $zip->getFromName('xl/styles.xml');
        $fmt = [];
        $xf = [];
        if ($xml !== false) {
            $st = simplexml_load_string($xml);
            if ($st !== false) {
                if (isset($st->numFmts)) {
                    foreach ($st->numFmts->numFmt as $n) {
                        $fmt[(int) $n['numFmtId']] = (string) $n['formatCode'];
                    }
                }
                if (isset($st->cellXfs)) {
                    foreach ($st->cellXfs->xf as $x) {
                        $xf[] = (int) $x['numFmtId'];
                    }
                }
            }
        }
        return ['fmt' => $fmt, 'xf' => $xf];
    }

    /** numFmtId de fecha/hora integrados de OOXML (14-22 fechas, 45-47 horas/duración). */
    private static function esFecha(int $s, array $est): bool {
        if (!isset($est['xf'][$s])) {
            return false;
        }
        $id = $est['xf'][$s];
        if (in_array($id, [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47], true)) {
            return true;
        }
        if (!isset($est['fmt'][$id])) {
            return false;
        }
        // Quitar literales entre corchetes ([Red], [$-409]...), comillas ("S/ ") y
        // caracteres escapados (\ ) antes de buscar letras de fecha/hora, si no un
        // formato de moneda como '"S/" #,##0.00' daría falso positivo por la "S".
        $code = preg_replace('/\[[^\]]*\]|"[^"]*"|\\\\./', '', $est['fmt'][$id]);
        return (bool) preg_match('/[dmyhs]/i', $code);
    }

    /**
     * Serie de fecha de Excel (días desde 1899-12-30, con el bug de compatibilidad
     * de Lotus 1-2-3 ya incluido en el offset) -> "Y-m-d".
     */
    private static function serieAFecha(float $serie, bool $base1904 = false): string {
        if ($serie <= 0) {
            return '';
        }
        if ($base1904) {
            $serie += 1462;
        }
        $unix = ($serie - 25569) * 86400;
        return gmdate('Y-m-d', (int) round($unix));
    }

    /** Referencia de celda ("A1", "AA23"...) -> índice de columna base-0. */
    private static function colAIndice(string $ref): int {
        $n = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $ch = $ref[$i];
            if ($ch < 'A' || $ch > 'Z') {
                break;
            }
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /**
     * Lee las filas de una hoja. El XML omite las celdas sin valor (<c r="A7"/>
     * seguido de <c r="D7"/>, saltándose B y C), así que la ÚNICA forma correcta
     * de mapear a columnas es indexar por el atributo r de cada <c>, nunca por
     * el orden de aparición — leer secuencialmente desplazaría todo el padrón.
     */
    private static function filas(ZipArchive $zip, string $ruta, array $sst, array $est, ?int $limite): array {
        $xml = $zip->getFromName($ruta);
        if ($xml === false) {
            throw new Exception('No se pudo leer la hoja seleccionada dentro del .xlsx.');
        }
        $sh = simplexml_load_string($xml);
        if ($sh === false) {
            throw new Exception('La hoja seleccionada no es un XML válido.');
        }

        // Ancho declarado por <dimension ref="A1:T559"/>; si falta, se ajusta al
        // vuelo con array_pad según la celda más a la derecha que aparezca.
        $ancho = 1;
        if (isset($sh->dimension['ref'])) {
            $ref = (string) $sh->dimension['ref'];
            $fin = (strpos($ref, ':') !== false) ? substr($ref, strpos($ref, ':') + 1) : $ref;
            $fin = preg_replace('/[0-9]+$/', '', $fin); // solo la parte de columna
            if ($fin !== '') {
                $ancho = self::colAIndice($fin) + 1;
            }
        }

        $filas = [];
        if (!isset($sh->sheetData->row)) {
            return $filas;
        }

        foreach ($sh->sheetData->row as $row) {
            $fila = array_fill(0, max($ancho, 1), '');

            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $idx = $ref !== '' ? self::colAIndice($ref) : -1;
                if ($idx < 0) {
                    continue;
                }
                if ($idx >= count($fila)) {
                    $fila = array_pad($fila, $idx + 1, '');
                }

                $t = isset($c['t']) ? (string) $c['t'] : 'n';
                $v = '';

                if ($t === 's') { // índice a sharedStrings (modo Excel)
                    $i = (int) $c->v;
                    $v = $sst[$i] ?? '';
                } elseif ($t === 'inlineStr') { // texto inline (modo LibreOffice/openpyxl)
                    foreach ($c->xpath('.//*[local-name()="t"]') as $tt) {
                        $v .= (string) $tt;
                    }
                } elseif ($t === 'str') { // resultado de fórmula, como texto
                    $v = (string) $c->v;
                } elseif ($t === 'b') {
                    $v = ((string) $c->v === '1') ? '1' : '0';
                } elseif ($t === 'e') { // #N/A, #REF!...
                    $v = '';
                } else { // numérico: puede ser un monto o una fecha serie
                    $bruto = (string) $c->v;
                    $s = isset($c['s']) ? (int) $c['s'] : 0;
                    $v = ($bruto !== '' && self::esFecha($s, $est))
                        ? self::serieAFecha((float) $bruto)
                        : $bruto;
                }

                $fila[$idx] = trim($v);
            }

            $filas[] = $fila;

            if ($limite !== null && count($filas) >= $limite) {
                break;
            }
        }

        return $filas;
    }
}
?>
