<?php
/** Abre la conexión con la base de datos indicada en la configuración. */
function conectar(array $config): PDO
{
    return new PDO($config['bd_dsn'], $config['bd_usuario'], $config['bd_clave'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}