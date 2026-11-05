<?php

require_once 'includes/funciones.php';
require_cliente();

$id_cliente  = (int)$_SESSION['id_cliente'];
$id_consulta = get('id');
$detalle     = null;
$lineas      = array();
$pagos       = array();

if ($id_consulta !== '') {
    $detalle = fila(
        sql_ventas() . ' WHERE v.id_venta = ? AND v.id_cliente = ?',
        array((int)$id_consulta, $id_cliente)
    );

    if (!$detalle) {
        flash('error', 'La compra solicitada no existe en tu cuenta.');
        redirigir('mis_compras.php');
    }

    $lineas = filas(
        'SELECT
             d.id_articulo,
             d.cantidad,
             a.nombre,
             a.precio,
             (d.cantidad * a.precio) AS subtotal
         FROM detalle_venta d
         JOIN articulo a ON a.id_articulo = d.id_articulo
         WHERE d.id_venta = ?
         ORDER BY a.nombre',
        array((int)$detalle['id_venta'])
    );

    $pagos = filas(
        'SELECT id_pago, tipo, monto, fecha_pago, estado
         FROM forma_pago
         WHERE id_venta = ?
         ORDER BY fecha_pago, id_pago',
        array((int)$detalle['id_venta'])
    );
}

$compras = filas(
    sql_ventas() . ' WHERE v.id_cliente = ? ORDER BY v.fecha DESC, v.id_venta DESC',
    array($id_cliente)
);

$titulo = $detalle ? 'Compra #' . $detalle['id_venta'] : 'Mis compras';
$activo = 'mis_compras.php';
include 'includes/encabezado_tienda.php';

if ($detalle):
    $saldo = $detalle['total'] - $detalle['pagado'];
?>
    <div class="encabezado">
        <div>
            <p class="subtitulo"><a href="mis_compras.php">← Volver a mis compras</a></p>
            <h1>Compra #<?= $detalle['id_venta'] ?> <?= etiqueta_estado($detalle['estado']) ?></h1>
        </div>
    </div>

    <section class="tarjeta">
        <div class="datos">
            <div>
                <div class="nombre">Fecha</div>
                <div class="valor"><?= fecha($detalle['fecha']) ?></div>
            </div>
            <div>
                <div class="nombre">Dirección de envío</div>
                <div class="valor"><?= h($detalle['direccion_envio']) ?></div>
                <div class="ayuda"><?= h($detalle['ciudad']) ?></div>
            </div>
            <div>
                <div class="nombre">Estado del pedido</div>
                <div class="valor"><?= etiqueta_estado($detalle['estado']) ?></div>
            </div>
        </div>

        <div class="resumen">
            <div>
                <div class="valor"><?= dinero($detalle['total']) ?></div>
                <div class="nombre">Total de la compra</div>
            </div>
            <div>
                <div class="valor"><?= dinero($detalle['pagado']) ?></div>
                <div class="nombre">Pagado (aprobado)</div>
            </div>
            <div>
                <div class="valor <?= $saldo > 0 && $detalle['estado'] !== 'Cancelada' ? 'stock-bajo' : '' ?>"><?= dinero($saldo) ?></div>
                <div class="nombre">Saldo por aprobar</div>
            </div>
        </div>
    </section>

    <section class="tarjeta">
        <h2>Artículos comprados</h2>
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th class="num">Precio unitario</th>
                        <th class="num">Cantidad</th>
                        <th class="num">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineas as $l): ?>
                        <tr>
                            <td><a href="articulo.php?id=<?= $l['id_articulo'] ?>"><?= h($l['nombre']) ?></a></td>
                            <td class="num"><?= dinero($l['precio']) ?></td>
                            <td class="num"><?= $l['cantidad'] ?></td>
                            <td class="num"><?= dinero($l['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num"><?= dinero($detalle['total']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="tarjeta">
        <h2>Pagos registrados</h2>
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Medio de pago</th>
                        <th>Estado</th>
                        <th class="num">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $p): ?>
                        <tr>
                            <td><?= $p['id_pago'] ?></td>
                            <td><?= fecha($p['fecha_pago']) ?></td>
                            <td><?= h($p['tipo']) ?></td>
                            <td><?= etiqueta_estado($p['estado']) ?></td>
                            <td class="num"><?= dinero($p['monto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php else: ?>
    <div class="encabezado">
        <div>
            <h1>Mis compras</h1>
            <p class="subtitulo">Historial y estado de tus pedidos realizados.</p>
        </div>
        <a class="boton" href="tienda.php">Ir al catálogo</a>
    </div>

    <div class="tarjeta">
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Dirección de envío</th>
                        <th>Estado</th>
                        <th class="num">Unid.</th>
                        <th class="num">Total</th>
                        <th class="num">Pagado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $c): ?>
                        <tr>
                            <td><a href="mis_compras.php?id=<?= $c['id_venta'] ?>">#<?= $c['id_venta'] ?></a></td>
                            <td class="fecha"><?= fecha($c['fecha']) ?></td>
                            <td><?= h($c['direccion_envio']) ?></td>
                            <td><?= etiqueta_estado($c['estado']) ?></td>
                            <td class="num"><?= $c['unidades'] ?></td>
                            <td class="num"><?= dinero($c['total']) ?></td>
                            <td class="num"><?= dinero($c['pagado']) ?></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="mis_compras.php?id=<?= $c['id_venta'] ?>">Ver detalle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$compras): ?>
                        <tr>
                            <td colspan="8" class="vacio">Aún no has realizado compras.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
