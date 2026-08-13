<?php
/**
 * Utilidades de cruce entre el padrón de alumnos y los reportes de pago:
 * normalización de nombres y parseo del concepto de pensión ("Pensión - Agosto - 2026").
 */
class M_Cruce {

    private const MESES = [
        'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
        'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
        'SETIEMBRE' => 9, 'SEPTIEMBRE' => 9, 'OCTUBRE' => 10,
        'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
    ];

    /**
     * Normaliza un nombre para poder comparar "APELLIDOS, Nombres" (padrón) contra
     * "APELLIDOS Nombres" (comprobantes): sin comas, sin tildes, mayúsculas,
     * espacios colapsados.
     *
     * NO usa iconv() a propósito: depende del locale del sistema operativo, así
     * que el mismo código podía normalizar distinto en local que en InfinityFree.
     *
     * La tabla strtr explícita es el fallback portable (no depende de ninguna
     * extensión). Si `intl` está disponible (Normalizer, vía ICU) se usa primero
     * porque cubre CUALQUIER diacrítico de forma genérica — hace falta: el padrón
     * real trae "Andrè" con acento grave, que una tabla de solo acentos agudos
     * (á é í ó ú) deja pasar intacto y rompe el cruce en silencio.
     */
    public static function normalizarNombre(?string $nombre): string {
        if ($nombre === null) {
            return '';
        }
        $s = str_replace(',', ' ', $nombre);

        if (class_exists('Normalizer')) {
            $descompuesto = Normalizer::normalize($s, Normalizer::FORM_D);
            if ($descompuesto !== false) {
                $s = preg_replace('/\p{Mn}/u', '', $descompuesto);
            }
        }

        // Fallback/complemento: cubre el caso sin `intl` y cualquier resto que la
        // descomposición Unicode no capture (ñ no es una "n con marca combinante").
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ñ' => 'N', 'Ç' => 'C',
        ]);

        $s = mb_strtoupper($s, 'UTF-8');
        // Cualquier cosa que no sea letra/dígito/espacio se vuelve espacio — esto
        // también absorbe los espacios duros (\xC2\xA0) típicos de exports web,
        // que de otro modo sobreviven al trim() normal.
        $s = preg_replace('/[^A-Z0-9 ]/u', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /**
     * Extrae mes y año de un CONCEPTO tipo "Pensión - Agosto - 2026". Devuelve
     * [mes, anio] o [null, null] si no matchea el patrón esperado.
     */
    public static function parsearConcepto(?string $concepto): array {
        if ($concepto === null) {
            return [null, null];
        }
        if (!preg_match('/Pensi[oó]n\s*-\s*([A-Za-zÁÉÍÓÚáéíóúñÑ]+)\s*-\s*(\d{4})/u', $concepto, $m)) {
            return [null, null];
        }
        $mesTexto = self::normalizarNombre($m[1]);
        $mes = self::MESES[$mesTexto] ?? null;
        $anio = (int) $m[2];
        return [$mes, $anio];
    }

    /** Clave derivada estable para la UNIQUE KEY de pagos: "PENSION-08-2026". */
    public static function conceptoClave(?int $mes, ?int $anio): string {
        if ($mes === null || $anio === null) {
            return 'DESCONOCIDO';
        }
        return 'PENSION-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '-' . $anio;
    }
}
?>