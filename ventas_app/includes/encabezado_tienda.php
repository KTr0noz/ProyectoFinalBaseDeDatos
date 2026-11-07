<?php

$usuario_tienda = usuario_actual();
$unidades_carrito = 0;
if (!empty($_SESSION['carrito']) && is_array($_SESSION['carrito'])) {
    foreach ($_SESSION['carrito'] as $cant_item) {
        $unidades_carrito += (int)$cant_item;
    }
}

$aviso_carrito = isset($_SESSION['aviso_carrito']) ? $_SESSION['aviso_carrito'] : null;
unset($_SESSION['aviso_carrito']);

// carrito.php lo pone en true cuando se pide confirmar "Vaciar carrito".
$confirmar_vaciar = !empty($confirmar_vaciar);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo) ?> · Tienda · Ventas por Internet</title>
    <link rel="preload" href="css/fuentes/ibm-plex-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<a class="saltar" href="#contenido">Saltar al contenido</a>

<?php if ($aviso_carrito): ?>
    <div class="velo">
        <div class="dialogo" role="dialog" aria-modal="true" aria-labelledby="aviso-carrito-titulo">
            <h2 id="aviso-carrito-titulo">Agregado al carrito</h2>
            <p>
                <?= h($aviso_carrito['nombre']) ?>
                (<?= $aviso_carrito['cantidad'] ?> <?= $aviso_carrito['cantidad'] === 1 ? 'unidad' : 'unidades' ?>).
                Tienes <?= $unidades_carrito ?> <?= $unidades_carrito === 1 ? 'unidad' : 'unidades' ?> en el carrito.
            </p>
            <div class="dialogo-botones">
                <a class="boton secundario" href="<?= h($aviso_carrito['volver']) ?>">Seguir comprando</a>
                <a class="boton" href="carrito.php">Ir al carrito</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($confirmar_vaciar): ?>
    <div class="velo">
        <div class="dialogo" role="alertdialog" aria-modal="true" aria-labelledby="vaciar-titulo">
            <h2 id="vaciar-titulo">¿Vaciar el carrito?</h2>
            <p>Se quitarán todos los artículos del carrito.</p>
            <div class="dialogo-botones">
                <a class="boton secundario" href="carrito.php">Cancelar</a>
                <form method="post" action="carrito.php">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="vaciar">
                    <button class="boton peligro">Vaciar carrito</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="app-tienda">
    <header class="barra-tienda">
        <div class="barra-tienda-interior">
            <a class="marca" href="tienda.php">
                <span class="logo">VI</span>
                Ventas por Internet
            </a>

            <nav class="nav-tienda" aria-label="Menú de tienda">
                <a href="tienda.php"<?= $activo === 'tienda.php' ? ' class="activo" aria-current="page"' : '' ?>>Catálogo</a>
                <a href="carrito.php"<?= $activo === 'carrito.php' ? ' class="activo" aria-current="page"' : '' ?>>
                    Carrito<?php if ($unidades_carrito > 0): ?> <span class="contador-carrito"><?= $unidades_carrito ?></span><?php endif; ?>
                </a>
                <a href="mis_compras.php"<?= $activo === 'mis_compras.php' ? ' class="activo" aria-current="page"' : '' ?>>Mis compras</a>
            </nav>

            <?php if ($usuario_tienda): ?>
                <div class="usuario-tienda">
                    <span class="nombre-usuario-tienda"><?= h($usuario_tienda['nombre'] . ' ' . $usuario_tienda['apellido']) ?></span>
                    <form method="post" action="salir.php">
                        <?= csrf_campo() ?>
                        <button class="boton-riel-salir">Salir</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="columna">
        <main class="contenido contenido-tienda" id="contenido">
            <?php mostrar_flash(); ?>

