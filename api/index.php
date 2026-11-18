<?php
// Punto de entrada de la API: todas las peticiones pasan por aquí
require 'ayudas.php';
require 'conexion.php';
require 'platos.php';

$config = require 'config.php';
configurarCors();

// 1. ¿Qué piden? El método y la ruta, sin la carpeta donde está la API
$metodo  = $_SERVER['REQUEST_METHOD'];                                  // GET, POST, PUT, DELETE…
$base    = dirname($_SERVER['SCRIPT_NAME']);                            // /carta/api
$camino  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);            // /carta/api/platos/3
$partes  = explode('/', trim(substr($camino, strlen($base)), '/'));     // ['platos', '3']
$recurso = $partes[0];                                                  // 'platos'
$id      = $partes[1] ?? null;                                          // '3' o null

if ($metodo === 'OPTIONS') {
    responder(204);
}

// 2. Reparto: cada ruta, a su función
if ($recurso === '' || $recurso === 'index.php') {
    responder(200, ['api' => 'La carta de La Alacena', 'version' => '1.0']);
}

if ($recurso === 'platos' && count($partes) === 1) {
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        metodoNoPermitido('GET, POST');
    }

    if ($metodo === 'POST') {
        exigirClave($config['claves_api'], 'cocina');
    }

    $pdo = conectar($config);
    match ($metodo) {
        'GET'   => listarPlatos($pdo),
        'POST'  => crearPlato($pdo, $base),
        default => metodoNoPermitido('GET, POST'),
    };
}

if ($recurso === 'platos' && count($partes) === 2 && ctype_digit($id)) {
    if (!in_array($metodo, ['GET', 'PUT', 'PATCH', 'DELETE'], true)) {
        metodoNoPermitido('GET, PUT, PATCH, DELETE');
    }

    if (in_array($metodo, ['PUT', 'PATCH'], true)) {
        exigirClave($config['claves_api'], 'cocina');
    } elseif ($metodo === 'DELETE') {
        exigirClave($config['claves_api'], 'gerencia');
    }

    $pdo = conectar($config);
    match ($metodo) {
        'GET'    => verPlato($pdo, (int) $id),
        'PUT'    => sustituirPlato($pdo, (int) $id),
        'PATCH'  => actualizarPlatoParcial($pdo, (int) $id),
        'DELETE' => borrarPlato($pdo, (int) $id),
        default  => metodoNoPermitido('GET, PUT, PATCH, DELETE'),
    };
}

error(404, 'Esa ruta no existe');