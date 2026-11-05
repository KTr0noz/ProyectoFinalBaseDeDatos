<?php

require_once 'includes/funciones.php';

cabeceras_no_cache();
verificar_inactividad_sesion();

if (!empty($_SESSION['id_cliente']) && !empty($_SESSION['rol'])) {
    redirigir($_SESSION['rol'] === 'admin' ? 'index.php' : 'tienda.php');
}

aplicar_rol_bd('visitante');

$errores = array();
$datos   = array(
    'id_cliente' => '',
    'nombre'     => '',
    'apellido'   => '',
    'email'      => '',
    'telefono'   => '',
    'direccion'  => '',
    'id_ciudad'  => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = array(
        'id_cliente' => post('id_cliente'),
        'nombre'     => post('nombre'),
        'apellido'   => post('apellido'),
        'email'      => mb_strtolower(post('email'), 'UTF-8'),
        'telefono'   => post('telefono'),
        'direccion'  => post('direccion'),
        'id_ciudad'  => post('id_ciudad'),
    );

    $clave        = isset($_POST['clave']) ? (string)$_POST['clave'] : '';
    $confirmacion = isset($_POST['confirmacion']) ? (string)$_POST['confirmacion'] : '';

    if (!csrf_validar()) {
        $errores[] = 'La sesión del formulario expiró. Intente nuevamente.';
    } else {
        validar_datos_cliente($datos, $errores);
        validar_clave($clave, $confirmacion, $errores, true);
    }

    if (!$errores) {
        $hash = password_hash($clave, PASSWORD_DEFAULT);

        try {
            consulta(
                'INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad, password_hash)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                array(
                    (int)$datos['id_cliente'],
                    $datos['nombre'],
                    $datos['apellido'],
                    $datos['email'],
                    $datos['telefono'],
                    $datos['direccion'],
                    (int)$datos['id_ciudad'],
                    $hash,
                )
            );

            iniciar_sesion_usuario((int)$datos['id_cliente'], 'cliente');
            flash('ok', 'Cuenta creada. Bienvenido a la tienda.');
            redirigir('tienda.php');
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
}

$ciudades = array();
foreach (filas('SELECT id_ciudad, nombre, departamento FROM ciudad ORDER BY nombre') as $c) {
    $ciudades[$c['id_ciudad']] = $c['nombre'] . ' (' . $c['departamento'] . ')';
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="cuerpo-auth">
<a class="saltar" href="#contenido">Saltar al contenido</a>

<header class="barra-auth">
    <div class="barra-auth-interior">
        <a class="marca marca-oscura" href="login.php">
            <span class="logo">VI</span>
            Ventas por Internet
        </a>
        <a class="enlace-auth" href="login.php">Ya tengo cuenta</a>
    </div>
</header>

<main class="contenedor-auth contenedor-auth-ancho" id="contenido">
    <section class="panel-auth" aria-labelledby="titulo-registro">
        <div class="cabecera-auth">
            <h1 id="titulo-registro">Completa tus datos para crear tu cuenta</h1>
            <p class="subtitulo">Tus compras y envíos quedarán asociados a tu documento y dirección.</p>
        </div>

        <?php mostrar_flash(); ?>
        <?php mostrar_errores($errores); ?>

        <form method="post" class="formulario">
            <?= csrf_campo() ?>

            <div>
                <label for="id_cliente">Cédula</label>
                <input id="id_cliente" name="id_cliente" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" required value="<?= h($datos['id_cliente']) ?>">
                <div class="ayuda">Solo números, mayor que 0.</div>
            </div>

            <div>
                <label for="id_ciudad">Ciudad</label>
                <select id="id_ciudad" name="id_ciudad" required>
                    <option value="">Seleccione…</option>
                    <?= opciones($ciudades, $datos['id_ciudad']) ?>
                </select>
            </div>

            <div>
                <label for="nombre">Nombres</label>
                <input id="nombre" name="nombre" maxlength="80" required value="<?= h($datos['nombre']) ?>">
            </div>

            <div>
                <label for="apellido">Apellidos</label>
                <input id="apellido" name="apellido" maxlength="80" required value="<?= h($datos['apellido']) ?>">
            </div>

            <div>
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" maxlength="120" autocomplete="email" required value="<?= h($datos['email']) ?>">
            </div>

            <div>
                <label for="telefono">Teléfono</label>
                <input id="telefono" name="telefono" maxlength="20" required value="<?= h($datos['telefono']) ?>">
            </div>

            <div class="completo">
                <label for="direccion">Dirección de entrega</label>
                <input id="direccion" name="direccion" maxlength="150" required value="<?= h($datos['direccion']) ?>">
            </div>

            <div>
                <label for="clave">Contraseña</label>
                <input id="clave" name="clave" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
                <div class="ayuda">Entre 8 y 72 caracteres.</div>
            </div>

            <div>
                <label for="confirmacion">Confirmar contraseña</label>
                <input id="confirmacion" name="confirmacion" type="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            </div>

            <div class="completo botones">
                <button class="boton">Registrarme</button>
                <a class="boton secundario" href="login.php">Ya tengo cuenta</a>
            </div>
        </form>
    </section>
</main>

<footer class="pie pie-auth">
    Proyecto #13 · Sistema de Ventas por Internet · Bases de Datos 2026-II · Universidad de los Llanos
</footer>
</body>
</html>
