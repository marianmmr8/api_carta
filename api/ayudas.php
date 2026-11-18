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

/** Respuesta de error: {"error": "…"} más los datos extra que se indiquen. */
function error(int $estado, string $mensaje, array $extra = []): never
{
    responder($estado, ['error' => $mensaje] + $extra);
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
/** Corta la petición con un 401 si no trae la cabecera Authorization: Bearer <clave>. */
function exigirClave(string $claveApi): void
{
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

    $cabecera = trim($cabecera);

    if (!hash_equals('Bearer ' . $claveApi, $cabecera)) {
        header('WWW-Authenticate: Bearer');
        error(401, 'Falta la clave de la API o no es válida');
    }
}