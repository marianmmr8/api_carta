<?php
// Punto de entrada de la API: todas las peticiones pasan por aquí
require 'ayudas.php';
require 'conexion.php';
require 'platos.php';

$config = require 'config.php';

// 1. ¿Qué piden? El método y la ruta, sin la carpeta donde está la API
$metodo  = $_SERVER['REQUEST_METHOD'];                                  // GET, POST, PUT, DELETE…
$base    = dirname($_SERVER['SCRIPT_NAME']);                            // /carta/api
$camino  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);            // /carta/api/platos/3
$partes  = explode('/', trim(substr($camino, strlen($base)), '/'));     // ['platos', '3']
$recurso = $partes[0];                                                  // 'platos'
$id      = $partes[1] ?? null;                                          // '3' o null

// 2. Las operaciones que cambian datos (las «no seguras») exigen la clave
if (!in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
    exigirClave($config['clave_api']);
}

// 3. Reparto: cada ruta, a su función
if ($recurso === '' || $recurso === 'index.php') {
    responder(200, ['api' => 'La carta de La Alacena', 'version' => '1.0']);
}

$pdo = conectar($config);

if ($recurso === 'platos' && count($partes) === 1) {
    match ($metodo) {
        'GET'   => listarPlatos($pdo),
        'POST'  => crearPlato($pdo, $base),
        default => metodoNoPermitido('GET, POST'),
    };
}

if ($recurso === 'platos' && count($partes) === 2 && ctype_digit($id)) {
    match ($metodo) {
        'GET'    => verPlato($pdo, (int) $id),
        'PUT'    => sustituirPlato($pdo, (int) $id),
        'DELETE' => borrarPlato($pdo, (int) $id),
        default  => metodoNoPermitido('GET, PUT, DELETE'),
    };
}

error(404, 'Esa ruta no existe');