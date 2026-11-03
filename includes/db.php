<?php
/*
 * Conexión a la base de datos con PDO (extensión pdo_pgsql).
 * Todas las consultas usan sentencias preparadas (?), así los datos que
 * escribe el usuario nunca se mezclan con el SQL (evita inyección SQL).
 */

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<!doctype html><meta charset="utf-8"><title>Sin conexión</title>';
            echo '<div style="font-family:sans-serif;max-width:640px;margin:60px auto;line-height:1.5">';
            echo '<h2>No se pudo conectar a la base de datos</h2>';
            echo '<p>Revisa que PostgreSQL esté encendido, que exista la base <b>' . htmlspecialchars(DB_NAME) . '</b> ';
            echo 'y que hayas ejecutado <code>usuario_app.sql</code>.</p>';
            echo '<pre style="background:#f4f4f4;padding:12px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>';
            echo '</div>';
            exit;
        }
    }
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el PDOStatement. */
function consulta($sql, $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Devuelve todas las filas de una consulta. */
function filas($sql, $params = array())
{
    return consulta($sql, $params)->fetchAll();
}

/** Devuelve una sola fila (o false si no hay). */
function fila($sql, $params = array())
{
    return consulta($sql, $params)->fetch();
}

/** Devuelve el primer valor de la primera fila. */
function valor($sql, $params = array())
{
    return consulta($sql, $params)->fetchColumn();
}
