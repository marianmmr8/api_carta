<?php
// Operaciones sobre los platos: una función por cada fila de la tabla de rutas

const CATEGORIAS = ['entrante', 'principal', 'postre'];

/** Convierte una fila de la tabla en el plato que devuelve la API, con sus tipos. */
function plato(array $fila): array
{
    return [
        'id'         => (int) $fila['id'],
        'nombre'     => $fila['nombre'],
        'categoria'  => $fila['categoria'],
        'precio'     => (float) $fila['precio'],
        'disponible' => (bool) $fila['disponible'],
    ];
}

/** Devuelve el plato, o responde 404 si no existe. */
function buscarPlato(PDO $pdo, int $id): array
{
    $consulta = $pdo->prepare('SELECT * FROM platos WHERE id = ?');
    $consulta->execute([$id]);
    $fila = $consulta->fetch();

    if (!$fila) {
        error(404, "No existe el plato $id");
    }
    return plato($fila);
}

/** GET /platos con filtros opcionales y paginación */
function listarPlatos(PDO $pdo): never
{
    $categoria = $_GET['categoria'] ?? '';
    $buscar = $_GET['buscar'] ?? '';

    if ($categoria === '') {
        $categoria = '';
    } elseif (!is_string($categoria) || !in_array($categoria, CATEGORIAS, true)) {
        error(400, 'La categoría debe ser entrante, principal o postre');
    }

    if (!is_string($buscar)) {
        error(400, 'El parámetro buscar debe ser texto');
    }
    $buscar = trim($buscar);

    $condiciones = [];
    $parametros = [];
    if ($categoria !== '') {
        $condiciones[] = 'categoria = ?';
        $parametros[] = $categoria;
    }
    if ($buscar !== '') {
        $condiciones[] = 'nombre LIKE ?';
        $parametros[] = '%' . $buscar . '%';
    }

    $where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
    $conteo = $pdo->prepare('SELECT COUNT(*) FROM platos' . $where);
    $conteo->execute($parametros);
    $total = (int) $conteo->fetchColumn();
    header("X-Total: $total");

    $sql = 'SELECT * FROM platos' . $where . ' ORDER BY id';
    $hayPaginacion = array_key_exists('pagina', $_GET) || array_key_exists('por_pagina', $_GET);
    if ($hayPaginacion) {
        $pagina = enteroConsulta('pagina', 1);
        $porPagina = enteroConsulta('por_pagina', 10, 100);
        if ($pagina > intdiv(PHP_INT_MAX, $porPagina)) {
            error(400, 'La página solicitada está fuera de rango');
        }
        $offset = ($pagina - 1) * $porPagina;
        $sql .= " LIMIT $porPagina OFFSET $offset";
    }

    $consulta = $pdo->prepare($sql);
    $consulta->execute($parametros);
    responder(200, array_map('plato', $consulta->fetchAll()));
}

/** Valida un parámetro de paginación entero y positivo. */
function enteroConsulta(string $nombre, int $defecto, ?int $maximo = null): int
{
    if (!array_key_exists($nombre, $_GET)) {
        return $defecto;
    }

    $valor = $_GET[$nombre];
    $entero = is_string($valor) && ctype_digit($valor)
        ? filter_var($valor, FILTER_VALIDATE_INT)
        : false;
    if ($entero === false || $entero < 1 || ($maximo !== null && $entero > $maximo)) {
        error(400, "$nombre debe ser un entero positivo" . ($maximo === null ? '' : " no mayor que $maximo"));
    }
    return $entero;
}

/** GET /platos/{id} */
function verPlato(PDO $pdo, int $id): never
{
    responder(200, buscarPlato($pdo, $id));
}
/** Comprueba los datos recibidos. Devuelve los datos limpios, o responde 422 con los errores. */
function validarPlato(array $d): array
{
    $errores = [];

    $nombre = is_string($d['nombre'] ?? null) ? trim($d['nombre']) : '';
    if ($nombre === '' || mb_strlen($nombre) > 80) {
        $errores['nombre'] = 'Obligatorio, de 1 a 80 caracteres';
    }

    $categoria = $d['categoria'] ?? null;
    if (!in_array($categoria, CATEGORIAS, true)) {
        $errores['categoria'] = 'Debe ser entrante, principal o postre';
    }

    $precio = $d['precio'] ?? null;
    if ((!is_int($precio) && !is_float($precio)) || $precio <= 0 || $precio >= 10000) {
        $errores['precio'] = 'Debe ser un número mayor que 0 y menor que 10000';
    }

    $disponible = $d['disponible'] ?? true;
    if (!is_bool($disponible)) {
        $errores['disponible'] = 'Debe ser true o false';
    }

    if ($errores) {
        error(422, 'Datos no válidos', ['errores' => $errores]);
    }

    return [
        'nombre'     => $nombre,
        'categoria'  => $categoria,
        'precio'     => round($precio, 2),
        'disponible' => $disponible,
    ];
}

/** POST /platos */
function crearPlato(PDO $pdo, string $base): never
{
    $p = validarPlato(leerJson());

    try {
        $consulta = $pdo->prepare('INSERT INTO platos (nombre, categoria, precio, disponible) VALUES (?, ?, ?, ?)');
        $consulta->execute([$p['nombre'], $p['categoria'], $p['precio'], (int) $p['disponible']]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {            // el nombre es UNIQUE y ya existe
            error(409, 'Ya existe un plato con ese nombre');
        }
        throw $e;
    }

    $id = (int) $pdo->lastInsertId();
    header("Location: $base/platos/$id");
    responder(201, buscarPlato($pdo, $id));
}
/** PUT /platos/{id} */
function sustituirPlato(PDO $pdo, int $id): never
{
    buscarPlato($pdo, $id);                         // responde 404 si no existe
    $p = validarPlato(leerJson());

    try {
        $consulta = $pdo->prepare('UPDATE platos SET nombre = ?, categoria = ?, precio = ?, disponible = ? WHERE id = ?');
        $consulta->execute([$p['nombre'], $p['categoria'], $p['precio'], (int) $p['disponible'], $id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            error(409, 'Ya existe un plato con ese nombre');
        }
        throw $e;
    }

    responder(200, buscarPlato($pdo, $id));
}

/** PATCH /platos/{id}: modifica solo los campos enviados. */
function actualizarPlatoParcial(PDO $pdo, int $id): never
{
    $actual = buscarPlato($pdo, $id);
    $entrada = leerJson();
    $permitidos = ['nombre', 'categoria', 'precio', 'disponible'];
    $cambios = array_intersect_key($entrada, array_flip($permitidos));

    if (!$cambios) {
        error(422, 'Envía al menos un campo válido para modificar', [
            'errores' => ['plato' => 'No se recibieron campos modificables'],
        ]);
    }

    $p = validarPlato(array_merge($actual, $cambios));
    $asignaciones = [];
    $valores = [];
    foreach (array_keys($cambios) as $campo) {
        $asignaciones[] = "$campo = ?";
        $valores[] = $campo === 'disponible' ? (int) $p[$campo] : $p[$campo];
    }
    $valores[] = $id;

    try {
        $consulta = $pdo->prepare(
            'UPDATE platos SET ' . implode(', ', $asignaciones) . ' WHERE id = ?'
        );
        $consulta->execute($valores);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            error(409, 'Ya existe un plato con ese nombre');
        }
        throw $e;
    }

    responder(200, buscarPlato($pdo, $id));
}

/** DELETE /platos/{id} */
function borrarPlato(PDO $pdo, int $id): never
{
    $consulta = $pdo->prepare('DELETE FROM platos WHERE id = ?');
    $consulta->execute([$id]);

    if ($consulta->rowCount() === 0) {
        error(404, "No existe el plato $id");
    }
    responder(204);
}