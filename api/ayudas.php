<?php
/** Envía la respuesta (código de estado y, si hay datos, JSON) y termina el programa. */
function responder(int $estado, mixed $datos = null): never
{
    http_response_code($estado);
    if ($datos !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/** Responde un error con formato Problem Details. */
function error(int $estado, string $mensaje, array $extra = []): never
{
    $titulos = [
        400 => 'Solicitud incorrecta',
        401 => 'No autenticado',
        403 => 'Prohibido',
        404 => 'No encontrado',
        405 => 'Método no permitido',
        409 => 'Conflicto',
        422 => 'Entidad no procesable',
        500 => 'Error interno del servidor',
    ];
    $problema = [
        'type'   => 'about:blank',
        'title'  => $titulos[$estado] ?? 'Error',
        'status' => $estado,
        'detail' => $mensaje,
    ] + $extra;

    http_response_code($estado);
    header('Content-Type: application/problem+json; charset=utf-8');
    echo json_encode($problema, JSON_UNESCAPED_UNICODE);
    exit;
}

// Cualquier excepción que nadie capture (por ejemplo, si falla la base de datos)
// se apunta en el registro del servidor y el cliente recibe un 500 sin datos técnicos
set_exception_handler(function (Throwable $e): void {
    error_log('API de la carta: ' . $e->getMessage());
    error(500, 'Error interno del servidor');
});
/** La ruta existe, pero no admite ese método: 405 con la lista de los que sí admite. */
function metodoNoPermitido(string $permitidos): never
{
    header("Allow: $permitidos");
    error(405, 'Método no permitido');
}
/** Devuelve el cuerpo de la petición como array. Si no es un objeto JSON, responde 400. */
function leerJson(): array
{
    $datos = json_decode(file_get_contents('php://input'));
    if (!$datos instanceof stdClass) {
        error(400, 'El cuerpo debe ser un objeto JSON válido');
    }
    return get_object_vars($datos);
}

/** Añade las cabeceras CORS necesarias para la página local servida con Live Server. */
function configurarCors(): void
{
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    $permitidos = [
        'http://localhost:5500',
        'http://127.0.0.1:5500',
    ];

    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Expose-Headers: X-Total, Location, Allow, WWW-Authenticate');

    if (in_array($origen, $permitidos, true)) {
        header("Access-Control-Allow-Origin: $origen");
    }
}

/** Autentica una clave y comprueba el permiso requerido. */
function exigirClave(array $clavesApi, string $permiso = 'cocina'): void
{
    $claveCocina = $clavesApi['cocina'] ?? null;
    $claveGerencia = $clavesApi['gerencia'] ?? null;
    if (!is_string($claveCocina) || $claveCocina === '' ||
        !is_string($claveGerencia) || $claveGerencia === '') {
        throw new RuntimeException('La configuración de claves de la API está incompleta');
    }

    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if ($cabecera === '' && function_exists('getallheaders')) {
        $cabeceras = getallheaders();
        if (is_array($cabeceras)) {
            foreach ($cabeceras as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0 && is_string($valor)) {
                    $cabecera = $valor;
                    break;
                }
            }
        }
    }

    if (!is_string($cabecera) || preg_match('/^Bearer\s+(\S+)$/i', trim($cabecera), $coincidencias) !== 1) {
        header('WWW-Authenticate: Bearer');
        error(401, 'Falta la clave de la API o no es válida');
    }

    $token = $coincidencias[1];
    $esCocina = hash_equals($claveCocina, $token);
    $esGerencia = hash_equals($claveGerencia, $token);

    if (!$esCocina && !$esGerencia) {
        header('WWW-Authenticate: Bearer');
        error(401, 'Falta la clave de la API o no es válida');
    }

    if ($permiso === 'gerencia' && !$esGerencia) {
        error(403, 'La clave de cocina no tiene permiso para borrar platos');
    }
}