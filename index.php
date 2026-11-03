<?php
require_once 'includes/funciones.php';

// Indicadores generales
$clientes   = valor('SELECT COUNT(*) FROM cliente');
$articulos  = valor('SELECT COUNT(*) FROM articulo');
$ventas     = valor('SELECT COUNT(*) FROM venta');
$pendientes = valor("SELECT COUNT(*) FROM venta WHERE estado = 'Pendiente'");
$recaudado  = valor("SELECT COALESCE(SUM(monto), 0) FROM forma_pago WHERE estado = 'Aprobado'");

$ultimas = filas(sql_ventas() . ' ORDER BY v.fecha DESC, v.id_venta DESC LIMIT 6');

$stock_bajo = filas(
    'SELECT a.id_articulo, a.nombre, a.stock, c.nombre AS categoria
     FROM articulo a JOIN categoria c ON c.id_categoria = a.id_categoria
     WHERE a.stock <= ? ORDER BY a.stock, a.nombre',
    array(STOCK_BAJO)
);

$titulo = 'Inicio';
$activo = 'index.php';
include 'includes/encabezado.php';
?>
<div class="encabezado">
    <div>
        <h1>Resumen de la tienda</h1>
        <p class="subtitulo">Estado actual de ventas, pagos e inventario.</p>
    </div>
</div>

<div class="indicadores">
    <div class="indicador"><div class="numero"><?= $ventas ?></div><div class="nombre">Ventas registradas</div></div>
    <div class="indicador"><div class="numero"><?= $pendientes ?></div><div class="nombre">Ventas pendientes</div></div>
    <div class="indicador"><div class="numero"><?= dinero($recaudado) ?></div><div class="nombre">Recaudado (pagos aprobados)</div></div>
    <div class="indicador"><div class="numero"><?= $clientes ?> / <?= $articulos ?></div><div class="nombre">Clientes / artículos</div></div>
</div>

<div class="columnas">
    <section class="tarjeta">
        <h2>Últimas ventas</h2>
        <div class="tabla-contenedor">
        <table>
            <thead><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($ultimas as $v): ?>
                <tr>
                    <td><a href="venta.php?id=<?= $v['id_venta'] ?>"><?= $v['id_venta'] ?></a></td>
                    <td><?= fecha($v['fecha']) ?></td>
                    <td><?= h($v['cliente']) ?></td>
                    <td><?= etiqueta_estado($v['estado']) ?></td>
                    <td class="num"><?= dinero($v['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ultimas): ?><tr><td colspan="5" class="vacio">Aún no hay ventas.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="tarjeta">
        <h2>Stock bajo (≤ <?= STOCK_BAJO ?> unidades)</h2>
        <table>
            <thead><tr><th>Artículo</th><th class="num">Stock</th></tr></thead>
            <tbody>
            <?php foreach ($stock_bajo as $a): ?>
                <tr>
                    <td><a href="articulos.php?accion=editar&id=<?= $a['id_articulo'] ?>"><?= h($a['nombre']) ?></a>
                        <div class="ayuda"><?= h($a['categoria']) ?></div></td>
                    <td class="num stock-bajo"><?= $a['stock'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$stock_bajo): ?><tr><td colspan="2" class="vacio">Todos los artículos tienen stock suficiente.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php include 'includes/pie.php'; ?>
