<?php

require_once 'includes/funciones.php';
require_cliente();

$id = (int)get('id');

$articulo = fila(
    'SELECT
         a.id_articulo,
         a.nombre,
         a.descripcion,
         a.precio,
         a.stock,
         a.id_categoria,
         c.nombre AS categoria
     FROM articulo a
     JOIN categoria c ON c.id_categoria = a.id_categoria
     WHERE a.id_articulo = ?',
    array($id)
);

if (!$articulo) {
    flash('error', 'El artículo solicitado no existe.');
    redirigir('tienda.php');
}

$en_carrito = isset($_SESSION['carrito'][$id]) ? (int)$_SESSION['carrito'][$id] : 0;

$titulo = $articulo['nombre'];
$activo = 'tienda.php';
include 'includes/encabezado_tienda.php';
?>

<div class="encabezado">
    <div>
        <p class="subtitulo"><a href="tienda.php">← Volver al catálogo</a></p>
        <h1><?= h($articulo['nombre']) ?></h1>
    </div>
</div>

<section class="ficha-articulo">
    <div class="ficha-articulo-info">
        <div class="etq"><?= h($articulo['categoria']) ?></div>

        <div class="ficha-precio"><?= dinero($articulo['precio']) ?></div>

        <?php if (!empty($articulo['descripcion'])): ?>
            <p class="ficha-descripcion"><?= h($articulo['descripcion']) ?></p>
        <?php else: ?>
            <p class="ayuda">Sin descripción adicional.</p>
        <?php endif; ?>

        <div class="ficha-stock">
            <?php if ($articulo['stock'] <= 0): ?>
                <span class="etiqueta rojo">Agotado</span>
            <?php elseif ($articulo['stock'] <= STOCK_BAJO): ?>
                <span class="stock-bajo">¡Últimas <?= $articulo['stock'] ?> unidades disponibles!</span>
            <?php else: ?>
                <span>Stock disponible: <b><?= $articulo['stock'] ?></b> unidades</span>
            <?php endif; ?>

            <?php if ($en_carrito > 0): ?>
                <div class="ayuda">Ya tienes <?= $en_carrito ?> <?= $en_carrito === 1 ? 'unidad' : 'unidades' ?> en tu <a href="carrito.php">carrito</a>.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ficha-articulo-compra">
        <?php if ($articulo['stock'] > 0): ?>
            <form method="post" action="carrito.php" class="formulario-compra">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="agregar">
                <input type="hidden" name="id_articulo" value="<?= $articulo['id_articulo'] ?>">

                <div>
                    <label for="cantidad">Cantidad</label>
                    <input
                        id="cantidad"
                        name="cantidad"
                        type="number"
                        min="1"
                        max="<?= $articulo['stock'] ?>"
                        step="1"
                        value="1"
                        required
                    >
                    <div class="ayuda">Máximo <?= $articulo['stock'] ?> disponibles.</div>
                </div>

                <button class="boton boton-completo">Agregar al carrito</button>
            </form>
        <?php else: ?>
            <p class="ayuda">Este artículo no tiene unidades disponibles en este momento.</p>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/pie.php'; ?>
