<?php

require_once 'includes/funciones.php';

cabeceras_no_cache();
verificar_inactividad_sesion();

if (!empty($_SESSION['id_cliente']) && !empty($_SESSION['rol'])) {
    redirigir($_SESSION['rol'] === 'admin' ? 'index.php' : 'tienda.php');
}

aplicar_rol_bd('visitante');

$errores = array();
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mb_strtolower(post('email'), 'UTF-8');
    $clave = isset($_POST['clave']) ? (string)$_POST['clave'] : '';

    if (!csrf_validar()) {
        $errores[] = 'La sesión del formulario expiró. Intente nuevamente.';
    } elseif (login_bloqueado($email)) {
        usleep(250000);
        $errores[] = 'Demasiados intentos fallidos. Espere 5 minutos antes de intentar nuevamente.';
    } else {
        // Hash señuelo fijo para igualar el tiempo de cómputo cuando el correo no existe o no tiene clave.
        $hash_relleno = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

        $cliente = false;
        if ($email !== '') {
            $cliente = fila(
                'SELECT id_cliente, email, password_hash, rol
                 FROM cliente
                 WHERE LOWER(email) = ?',
                array($email)
            );
        }

        $hash_comparar = ($cliente && !empty($cliente['password_hash']))
            ? $cliente['password_hash']
            : $hash_relleno;

        $verificado = ($clave !== '' && strlen($clave) <= 72)
            ? password_verify($clave, $hash_comparar)
            : false;

        if ($cliente && !empty($cliente['password_hash']) && $verificado) {
            registrar_intento_login($email, true);
            iniciar_sesion_usuario($cliente['id_cliente'], $cliente['rol']);
            redirigir($cliente['rol'] === 'admin' ? 'index.php' : 'tienda.php');
        }

        registrar_intento_login($email, false);
        usleep(250000);
        $errores[] = 'Correo o contraseña incorrectos.';
    }
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Ventas por Internet</title>
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
    </div>
</header>

<main class="contenedor-auth" id="contenido">
    <section class="panel-auth" aria-labelledby="titulo-login">
        <div class="cabecera-auth">
            <h1 id="titulo-login">Ingresa a tu cuenta</h1>
            <p class="subtitulo">Escribe tu correo y contraseña para comprar o administrar.</p>
        </div>

        <?php mostrar_flash(); ?>
        <?php mostrar_errores($errores); ?>

        <form method="post" class="formulario-auth">
            <?= csrf_campo() ?>

            <div>
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" maxlength="120" autocomplete="email" required autofocus value="<?= h($email) ?>">
            </div>

            <div>
                <label for="clave">Contraseña</label>
                <input id="clave" name="clave" type="password" minlength="8" maxlength="72" autocomplete="current-password" required>
            </div>

            <div class="botones-auth">
                <button class="boton boton-completo">Continuar</button>
                <a class="boton secundario boton-completo" href="registro.php">Crear cuenta</a>
            </div>
        </form>
    </section>
</main>

<footer class="pie pie-auth">
    Proyecto #13 · Sistema de Ventas por Internet · Bases de Datos 2026-II · Universidad de los Llanos
</footer>
</body>
</html>
