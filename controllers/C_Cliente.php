<?php
// Iniciar sesión PHP para control de autenticación
session_start();

// Definir cabecera de respuesta JSON
header('Content-Type: application/json');

// Validar autorización del usuario
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["success" => false, "mensaje" => "No autorizado."]);
    exit;
}

// Cargar dependencias de Cliente
require_once dirname(__DIR__) . '/entities/Cliente.php';
require_once dirname(__DIR__) . '/models/M_Cliente.php';

// Obtener la acción solicitada por GET
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Capturar datos del cuerpo de la petición (JSON) o POST
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Instanciar el modelo de Cliente usando Singleton
$model = M_Cliente::singleton();

// Enrutar según la acción solicitada
switch ($action) {
    
    // Lista todos los clientes registrados localmente
    case 'listar':
        echo json_encode($model->listarClientes());
        break;

    // Realiza únicamente la consulta externa a la API de Perú (Reniec/Sunat) sin guardar en base de datos
    case 'buscar_api_only':
        $numero_documento = isset($input['numero_documento']) ? trim($input['numero_documento']) : '';
        
        // Validar longitud del DNI (8 dígitos) o RUC (11 dígitos)
        if (empty($numero_documento) || (strlen($numero_documento) !== 8 && strlen($numero_documento) !== 11)) {
            echo json_encode(["success" => false, "mensaje" => "El número de documento debe tener exactamente 8 u 11 dígitos."]);
            exit;
        }

        // Determinar el tipo de documento según el tamaño
        $tipo = (strlen($numero_documento) === 8) ? 'dni' : 'ruc';
        $endpoint = "https://apiperu.dev/api/" . $tipo;
        
        // Cargar archivo de configuración que contiene el token de la API
        $apiConfig = require dirname(__DIR__) . '/config/api.php';
        $token = isset($apiConfig['apiperu_token']) ? $apiConfig['apiperu_token'] : '';

        if (empty($token)) {
            echo json_encode(["success" => false, "mensaje" => "Error de configuración: Token de API no configurado en el servidor."]);
            exit;
        }

        $params = json_encode([$tipo => $numero_documento]);
        
        // Inicializar curl para la llamada HTTP
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo json_encode(["success" => false, "mensaje" => "Error de conexión con la API de consulta: " . $err]);
            exit;
        }

        $resData = json_decode($response, true);

        // Validar si la respuesta de la API es exitosa
        if (!$resData || !isset($resData['success']) || !$resData['success']) {
            $msg = isset($resData['message']) ? $resData['message'] : "Documento no encontrado en el padrón de SUNAT/RENIEC.";
            echo json_encode([
                "success" => false, 
                "mensaje" => $msg . " (El origen de datos es el padrón de SUNAT y podría no estar actualizado para ingresos muy recientes)."
            ]);
            exit;
        }

        $apiData = $resData['data'];
        $mapped = [];

        // Mapear los datos retornados para el formulario frontend
        if ($tipo === 'dni') {
            // Extraer nombres y apellidos por separado
            $soloNombres = '';
            $soloApellidos = '';

            if (isset($apiData['nombres']) && !empty($apiData['nombres'])) {
                $soloNombres = trim($apiData['nombres']);
                $soloApellidos = trim(($apiData['apellido_paterno'] ?? '') . ' ' . ($apiData['apellido_materno'] ?? ''));
            } elseif (isset($apiData['nombre_completo']) && !empty($apiData['nombre_completo'])) {
                if (strpos($apiData['nombre_completo'], ',') !== false) {
                    $partes = explode(',', $apiData['nombre_completo'], 2);
                    $soloApellidos = trim($partes[0]);
                    $soloNombres = trim($partes[1]);
                } else {
                    $soloNombres = $apiData['nombre_completo'];
                }
            }

            // 'nombre' = nombre completo para formularios de un solo campo (formato: APELLIDOS, NOMBRES)
            if (!empty($soloApellidos)) {
                $mapped['nombre'] = $soloApellidos . ', ' . $soloNombres;
            } else {
                $mapped['nombre'] = $soloNombres;
            }
            // Campos separados para formularios con campos individuales
            $mapped['nombres'] = $soloNombres;
            $mapped['apellidos'] = $soloApellidos;
            $mapped['direccion'] = $apiData['direccion'] ?? '';
            $mapped['tipo_cliente'] = 1; // Persona Natural
        } else {
            // RUC
            $estado = isset($apiData['estado']) ? strtoupper(trim($apiData['estado'])) : '';
            $condicion = isset($apiData['condicion']) ? strtoupper(trim($apiData['condicion'])) : '';

            // Validar estado activo y habido en SUNAT
            if (!empty($estado) && $estado !== 'ACTIVO') {
                echo json_encode([
                    "success" => false, 
                    "mensaje" => "El contribuyente tiene estado no activo: " . $estado . "."
                ]);
                exit;
            }
            if (!empty($condicion) && $condicion !== 'HABIDO') {
                echo json_encode([
                    "success" => false, 
                    "mensaje" => "El contribuyente tiene condición no habida: " . $condicion . "."
                ]);
                exit;
            }

            $mapped['nombre'] = $apiData['nombre_o_razon_social'] ?? '';
            $mapped['nombres'] = $mapped['nombre'];
            $mapped['apellidos'] = '';
            $mapped['direccion'] = $apiData['direccion'] ?? '';
            $mapped['tipo_cliente'] = strpos($numero_documento, '20') === 0 ? 2 : 1; // 2 = Jurídica (20), 1 = Natural (10)
        }

        echo json_encode([
            "success" => true,
            "data" => $mapped
        ]);
        break;

    // Consulta el DNI/RUC en base de datos local; si no existe, consulta la API de Perú y lo registra automáticamente
    case 'consultar_api':
        $numero_documento = isset($input['numero_documento']) ? trim($input['numero_documento']) : '';
        
        if (empty($numero_documento) || (strlen($numero_documento) !== 8 && strlen($numero_documento) !== 11)) {
            echo json_encode(["success" => false, "mensaje" => "El número de documento debe tener exactamente 8 u 11 dígitos."]);
            exit;
        }

        // 1. Intentar buscar localmente en la base de datos
        $existente = $model->obtenerClientePorDocumento($numero_documento);
        if ($existente) {
            echo json_encode([
                "success" => true,
                "mensaje" => "Cliente encontrado en la base de datos local.",
                "cliente" => $existente
            ]);
            exit;
        }

        // 2. Si no existe localmente, consultar a la API apiperu.dev
        $tipo = (strlen($numero_documento) === 8) ? 'dni' : 'ruc';
        $endpoint = "https://apiperu.dev/api/" . $tipo;
        
        $apiConfig = require dirname(__DIR__) . '/config/api.php';
        $token = isset($apiConfig['apiperu_token']) ? $apiConfig['apiperu_token'] : '';

        if (empty($token)) {
            echo json_encode(["success" => false, "mensaje" => "Error de configuración: Token de API no configurado en el servidor."]);
            exit;
        }

        $params = json_encode([$tipo => $numero_documento]);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            echo json_encode(["success" => false, "mensaje" => "Error de conexión con la API de consulta: " . $err]);
            exit;
        }

        $resData = json_decode($response, true);

        if (!$resData || !isset($resData['success']) || !$resData['success']) {
            $msg = isset($resData['message']) ? $resData['message'] : "Documento no encontrado en el padrón de SUNAT/RENIEC.";
            echo json_encode([
                "success" => false, 
                "mensaje" => $msg . " (El origen de datos es el padrón de SUNAT y podría no estar actualizado para ingresos muy recientes)."
            ]);
            exit;
        }

        // 3. Extraer y mapear la información obtenida
        $apiData = $resData['data'];
        $tipo_documento = ($tipo === 'dni') ? 1 : 2;
        
        $nombres_razon_social = '';
        $apellidos = '';
        $direccion = '';
        $tipo_cliente = 1;

        if ($tipo === 'dni') {
            // Separar nombres y apellidos correctamente
            if (isset($apiData['nombres']) && !empty($apiData['nombres'])) {
                $nombres_razon_social = trim($apiData['nombres']);
                $apellidos = trim(($apiData['apellido_paterno'] ?? '') . ' ' . ($apiData['apellido_materno'] ?? ''));
            } elseif (isset($apiData['nombre_completo']) && !empty($apiData['nombre_completo'])) {
                // nombre_completo suele venir como "APELLIDOS, NOMBRES" o "NOMBRES APELLIDOS"
                if (strpos($apiData['nombre_completo'], ',') !== false) {
                    $partes = explode(',', $apiData['nombre_completo'], 2);
                    $apellidos = trim($partes[0]);
                    $nombres_razon_social = trim($partes[1]);
                } else {
                    $nombres_razon_social = $apiData['nombre_completo'];
                }
            }
            $direccion = $apiData['direccion'] ?? '';
            $tipo_cliente = 1;
        } else {
            // RUC
            $estado = isset($apiData['estado']) ? strtoupper(trim($apiData['estado'])) : '';
            $condicion = isset($apiData['condicion']) ? strtoupper(trim($apiData['condicion'])) : '';

            // Validaciones del RUC
            if (!empty($estado) && $estado !== 'ACTIVO') {
                echo json_encode([
                    "success" => false, 
                    "mensaje" => "No se puede registrar al cliente. El contribuyente tiene estado: " . $estado . "."
                ]);
                exit;
            }
            if (!empty($condicion) && $condicion !== 'HABIDO') {
                echo json_encode([
                    "success" => false, 
                    "mensaje" => "No se puede registrar al cliente. El contribuyente tiene condición de domicilio: " . $condicion . "."
                ]);
                exit;
            }

            $nombres_razon_social = $apiData['nombre_o_razon_social'] ?? '';
            $direccion = $apiData['direccion'] ?? '';
            $tipo_cliente = strpos($numero_documento, '20') === 0 ? 2 : 1;
        }

        // 4. Registrar automáticamente al nuevo cliente en la base de datos local
        $cliente = new Cliente($tipo_documento, $numero_documento, $nombres_razon_social, $apellidos, $direccion, '', $tipo_cliente);
        
        $resultado = $model->registrarCliente($cliente);
        if ($resultado === true) {
            $nuevoCliente = $model->obtenerClientePorDocumento($numero_documento);
            echo json_encode([
                "success" => true,
                "mensaje" => "Cliente consultado y registrado automáticamente.",
                "cliente" => $nuevoCliente
            ]);
        } else {
            $errorMsg = "Se obtuvieron los datos pero no se pudo registrar al cliente en la base de datos local.";
            if (is_string($resultado) && stripos($resultado, 'Duplicate entry') !== false) {
                $errorMsg = "El número de documento '$numero_documento' ya se encuentra registrado.";
            } elseif (is_string($resultado)) {
                $errorMsg = $resultado;
            }
            echo json_encode(["success" => false, "mensaje" => $errorMsg]);
        }
        break;

    // Registra un cliente de manera manual
    case 'crear':
        $tipo_documento = isset($input['tipo_documento']) ? intval($input['tipo_documento']) : 1; 
        $numero_documento = isset($input['numero_documento']) ? trim($input['numero_documento']) : '';
        $nombres_razon_social = isset($input['nombres_razon_social']) ? trim($input['nombres_razon_social']) : '';
        $apellidos = isset($input['apellidos']) ? trim($input['apellidos']) : '';
        $direccion = isset($input['direccion']) ? trim($input['direccion']) : '';
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
        $tipo_cliente = isset($input['tipo_cliente']) ? intval($input['tipo_cliente']) : 1;

        // Validación de campos requeridos
        if (empty($numero_documento) || empty($nombres_razon_social)) {
            echo json_encode(["success" => false, "mensaje" => "N° de documento y Nombres/Razón Social son obligatorios."]);
            exit;
        }

        // Crear la entidad cliente
        $cliente = new Cliente($tipo_documento, $numero_documento, $nombres_razon_social, $apellidos, $direccion, $telefono, $tipo_cliente);
        
        // Intentar registrar el cliente
        $resultado = $model->registrarCliente($cliente);
        if ($resultado === true) {
            $nuevoCliente = $model->obtenerClientePorDocumento($numero_documento);
            echo json_encode([
                "success" => true, 
                "mensaje" => "Cliente registrado con éxito.",
                "cliente" => $nuevoCliente
            ]);
        } else {
            $errorMsg = "Error al registrar el cliente.";
            if (is_string($resultado) && stripos($resultado, 'Duplicate entry') !== false) {
                $errorMsg = "El número de documento '$numero_documento' ya se encuentra registrado. Si el cliente existe, búsquelo por su documento.";
            } elseif (is_string($resultado)) {
                $errorMsg = $resultado;
            }
            echo json_encode(["success" => false, "mensaje" => $errorMsg]);
        }
        break;

    // Actualiza los datos de un cliente
    case 'actualizar':
        $id_cliente = isset($input['id_cliente']) ? intval($input['id_cliente']) : 0;
        $tipo_documento = isset($input['tipo_documento']) ? intval($input['tipo_documento']) : 1;
        $numero_documento = isset($input['numero_documento']) ? trim($input['numero_documento']) : '';
        $nombres_razon_social = isset($input['nombres_razon_social']) ? trim($input['nombres_razon_social']) : '';
        $apellidos = isset($input['apellidos']) ? trim($input['apellidos']) : '';
        $direccion = isset($input['direccion']) ? trim($input['direccion']) : '';
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
        $tipo_cliente = isset($input['tipo_cliente']) ? intval($input['tipo_cliente']) : 1;

        // Validaciones previas
        if ($id_cliente <= 0 || empty($numero_documento) || empty($nombres_razon_social)) {
            echo json_encode(["success" => false, "mensaje" => "Datos inválidos o incompletos."]);
            exit;
        }

        // Crear entidad y guardar id
        $cliente = new Cliente($tipo_documento, $numero_documento, $nombres_razon_social, $apellidos, $direccion, $telefono, $tipo_cliente);
        $cliente->id_cliente = $id_cliente;

        // Ejecutar actualización
        if ($model->actualizarCliente($cliente)) {
            echo json_encode(["success" => true, "mensaje" => "Cliente actualizado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al actualizar el cliente."]);
        }
        break;

    // Realiza la eliminación física o lógica de un cliente
    case 'eliminar':
        $id_cliente = isset($input['id_cliente']) ? intval($input['id_cliente']) : 0;

        if ($id_cliente <= 0) {
            echo json_encode(["success" => false, "mensaje" => "ID de cliente inválido."]);
            exit;
        }

        // Eliminar cliente del sistema
        if ($model->eliminarCliente($id_cliente)) {
            echo json_encode(["success" => true, "mensaje" => "Cliente eliminado con éxito."]);
        } else {
            echo json_encode(["success" => false, "mensaje" => "Error al eliminar el cliente."]);
        }
        break;

    default:
        echo json_encode(["success" => false, "mensaje" => "Acción no válida."]);
        break;
}
?>
