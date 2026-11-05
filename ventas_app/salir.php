<?php

require_once 'includes/funciones.php';

cabeceras_no_cache();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validar()) {
    if (!empty($_SESSION['rol'])) {
        redirigir($_SESSION['rol'] === 'admin' ? 'index.php' : 'tienda.php');
    }
    redirigir('login.php');
}

cerrar_sesion_usuario();
flash('info', 'Has cerrado sesión.');
redirigir('login.php');
