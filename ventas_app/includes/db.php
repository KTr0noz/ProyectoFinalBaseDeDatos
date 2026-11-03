<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/includes/db.php
 * DESCRIPCIÓN:
 *   Capa de acceso a datos con PDO (extensión pdo_pgsql).
 *   Todas las consultas utilizan sentencias preparadas (?) para separar el
 *   código SQL de los datos del usuario y prevenir inyección SQL.
 * ============================================================================
 */

/**
 * Obtiene la conexión única (Singleton) a PostgreSQL mediante PDO.
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
            echo '<p>Revisa que PostgreSQL esté activo y que la base <b>' . htmlspecialchars(DB_NAME) . '</b> esté disponible.</p>';
            echo '<pre style="background:#f4f4f4;padding:12px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>';
            echo '</div>';
            exit;
        }
    }

    return $pdo;
}

/**
 * Prepara y ejecuta una consulta SQL con parámetros seguros (?).
 * Devuelve el objeto PDOStatement resultante.
 */
function consulta($sql, $params = array())
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($params);
    return $sentencia;
}

/**
 * Ejecuta una consulta y devuelve todas las filas como arreglo asociativo.
 */
function filas($sql, $params = array())
{
    return consulta($sql, $params)->fetchAll();
}

/**
 * Ejecuta una consulta y devuelve una única fila (o false si no existe).
 */
function fila($sql, $params = array())
{
    return consulta($sql, $params)->fetch();
}

/**
 * Ejecuta una consulta y devuelve el primer valor de la primera fila
 * (útil para COUNT, SUM o RETURNING).
 */
function valor($sql, $params = array())
{
    return consulta($sql, $params)->fetchColumn();
}
