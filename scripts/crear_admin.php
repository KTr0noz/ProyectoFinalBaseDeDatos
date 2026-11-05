<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Acceso denegado: este script solo puede ejecutarse desde CLI.\n");
}

require_once __DIR__ . '/../ventas_app/includes/funciones.php';

$requeridas = array(
    'ADMIN_EMAIL',
    'ADMIN_PASSWORD',
    'ADMIN_CEDULA',
    'ADMIN_NOMBRE',
    'ADMIN_APELLIDO',
    'ADMIN_CIUDAD',
);

$faltantes = array();
foreach ($requeridas as $nombre_var) {
    $val = getenv($nombre_var);
    if ($val === false || trim($val) === '') {
        $faltantes[] = $nombre_var;
    }
}

if ($faltantes) {
    fwrite(STDERR, 'Error: faltan variables de entorno obligatorias: ' . implode(', ', $faltantes) . ".\n");
    exit(1);
}

$email         = mb_strtolower(trim((string)getenv('ADMIN_EMAIL')), 'UTF-8');
$password      = (string)getenv('ADMIN_PASSWORD');
$cedula        = trim((string)getenv('ADMIN_CEDULA'));
$nombre        = trim((string)getenv('ADMIN_NOMBRE'));
$apellido      = trim((string)getenv('ADMIN_APELLIDO'));
$nombre_ciudad = trim((string)getenv('ADMIN_CIUDAD'));
$telefono      = getenv('ADMIN_TELEFONO') ? trim((string)getenv('ADMIN_TELEFONO')) : '3000000000';
$direccion     = getenv('ADMIN_DIRECCION') ? trim((string)getenv('ADMIN_DIRECCION')) : 'Sede Principal';

$errores = array();
validar_entero($cedula, 'ADMIN_CEDULA', $errores);
validar_texto($nombre, 'ADMIN_NOMBRE', 80, $errores);
validar_texto($apellido, 'ADMIN_APELLIDO', 80, $errores);
validar_texto($email, 'ADMIN_EMAIL', 120, $errores);
validar_email($email, 'ADMIN_EMAIL', $errores);
validar_texto($nombre_ciudad, 'ADMIN_CIUDAD', 80, $errores);
validar_clave($password, $password, $errores, false);

if ($errores) {
    fwrite(STDERR, "Error de validación en las variables del administrador:\n - " . implode("\n - ", $errores) . "\n");
    exit(1);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $ciudad = fila(
        'SELECT id_ciudad FROM ciudad WHERE LOWER(nombre) = LOWER(?) LIMIT 1',
        array($nombre_ciudad)
    );

    if ($ciudad) {
        $id_ciudad = (int)$ciudad['id_ciudad'];
    } else {
        $id_ciudad = (int)valor(
            'INSERT INTO ciudad (nombre, departamento, pais, codigo_postal)
             VALUES (?, ?, ?, ?)
             RETURNING id_ciudad',
            array($nombre_ciudad, 'Meta', 'Colombia', '500001')
        );
        fwrite(STDOUT, "Ciudad '$nombre_ciudad' no existía; se insertó automáticamente (Meta, Colombia).\n");
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $existente = fila(
        'SELECT id_cliente FROM cliente WHERE LOWER(email) = LOWER(?)',
        array($email)
    );

    if ($existente) {
        consulta(
            "UPDATE cliente
             SET id_cliente = ?,
                 nombre = ?,
                 apellido = ?,
                 email = ?,
                 id_ciudad = ?,
                 password_hash = ?,
                 rol = 'admin'
             WHERE LOWER(email) = LOWER(?)",
            array((int)$cedula, $nombre, $apellido, $email, $id_ciudad, $hash, $email)
        );
        fwrite(STDOUT, "Cuenta de administrador actualizada para $email.\n");
    } else {
        consulta(
            "INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad, password_hash, rol)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin')",
            array((int)$cedula, $nombre, $apellido, $email, $telefono, $direccion, $id_ciudad, $hash)
        );
        fwrite(STDOUT, "Cuenta de administrador creada para $email.\n");
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Error al configurar el administrador: ' . mensaje_error_bd($e) . "\n");
    exit(1);
}
