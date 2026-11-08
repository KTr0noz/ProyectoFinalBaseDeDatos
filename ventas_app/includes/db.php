<?php
/**
 * Conexión a PostgreSQL con PDO y cuatro atajos para consultar.
 * Todas las consultas usan "?" y los valores van aparte (sentencias preparadas),
 * así nada de lo que escribe el usuario se mezcla con el SQL (evita inyección SQL).
 */

/** La conexión única a la base (se abre la primera vez que se pide). */
function db()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME,
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // los errores lanzan PDOException
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // filas como arreglo columna => valor
                )
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<!doctype html><meta charset="utf-8"><title>Sin conexión</title>'
               . '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.5">'
               . '<h2>No se pudo conectar a la base de datos</h2>'
               . '<p>Revisa que PostgreSQL esté activo y que la base <b>' . htmlspecialchars(DB_NAME) . '</b> esté disponible.</p>'
               . '<pre style="background:#f4f4f4;padding:12px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>'
               . '</div>';
            exit;
        }
    }

    return $pdo;
}

/** Ejecuta una consulta con sus valores y devuelve el resultado (PDOStatement). */
function consulta($sql, $params = array())
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($params);
    return $sentencia;
}

/** Todas las filas del resultado. */
function filas($sql, $params = array())
{
    return consulta($sql, $params)->fetchAll();
}

/** La primera fila, o false si no hay ninguna. */
function fila($sql, $params = array())
{
    return consulta($sql, $params)->fetch();
}

/** El primer dato de la primera fila (COUNT, SUM, RETURNING id...). */
function valor($sql, $params = array())
{
    return consulta($sql, $params)->fetchColumn();
}
