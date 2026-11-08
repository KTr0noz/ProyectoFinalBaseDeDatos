<?php
/** Cierra la sesión. Solo acepta el botón "Salir" (POST con token CSRF), no un enlace. */

require_once 'includes/funciones.php';
cabeceras_no_cache();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validar()) {
    redirigir(empty($_SESSION['rol']) ? 'login.php' : pagina_inicio($_SESSION['rol']));
}

cerrar_sesion_usuario();
flash('info', 'Has cerrado sesión.');
redirigir('login.php');
