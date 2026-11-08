<?php
/**
 * Ficha de una venta: datos, artículos, pagos y cambio de estado.
 *   - Solo una venta Pendiente deja agregar o quitar artículos.
 *   - Al agregar una línea, el disparador trg_descontar_stock_venta revisa y descuenta el stock.
 *   - Al quitar una línea o cancelar la venta, las unidades vuelven al inventario.
 *   - Los estados solo avanzan como dice $TRANSICIONES_VENTA (funciones.php).
 */

require_once 'includes/funciones.php';
require_admin();

$id    = (int)get('id');
$venta = buscar_venta($id);
if (!$venta) {
    flash('error', 'La venta no existe.');
    redirigir('ventas.php');
}

$aqui     = 'venta.php?id=' . $id;
$editable = ($venta['estado'] === 'Pendiente');
$errores  = array();

// ---------- Formularios enviados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf($aqui);
    $accion = post('accion');

    // 1. Cambiar estado y/o dirección de envío
    if ($accion === 'actualizar') {
        $actual    = $venta['estado'];
        $estado    = post('estado');
        $direccion = post('direccion_envio');

        validar_opcion($estado, 'Estado', $ESTADOS_VENTA, $errores);
        validar_texto($direccion, 'Dirección de envío', 150, $errores);

        if (!$errores && $estado !== $actual) {
            if (!in_array($estado, $TRANSICIONES_VENTA[$actual], true)) {
                $errores[] = "No se puede pasar una venta de $actual a $estado.";
            } elseif ($estado === 'Pagada' && !venta_pagada($venta)) {
                $errores[] = 'La venta no se puede marcar como Pagada: tiene saldo pendiente de '
                           . dinero($venta['total'] - $venta['pagado']) . ' o no tiene artículos.';
            }
        }

        if (!$errores) {
            $cancelar = ($estado === 'Cancelada' && $actual !== 'Cancelada');
            try {
                en_transaccion(function () use ($id, $estado, $direccion, $cancelar) {
                    if ($cancelar) {
                        devolver_stock_venta($id); // antes de marcarla Cancelada
                    }
                    consulta('UPDATE venta SET estado = ?, direccion_envio = ? WHERE id_venta = ?', array($estado, $direccion, $id));
                });
                flash('ok', $cancelar ? 'Venta cancelada y su stock devuelto al inventario.' : 'Venta actualizada.');
                redirigir($aqui);
            } catch (PDOException $e) {
                $errores[] = mensaje_error_bd($e);
            }
        }
    }

    // 2. Agregar un artículo
    if ($accion === 'agregar') {
        $id_articulo = post('id_articulo');
        $cantidad    = post('cantidad');

        if (!$editable) {
            $errores[] = 'Solo se pueden agregar artículos a una venta Pendiente.';
        }
        if (!fila('SELECT 1 FROM articulo WHERE id_articulo = ?', array((int)$id_articulo))) {
            $errores[] = 'Seleccione un artículo.';
        }
        validar_entero($cantidad, 'Cantidad', $errores, 1, 100000);

        if (!$errores) {
            try {
                // El disparador trg_descontar_stock_venta revisa y descuenta el stock (o rechaza la línea)
                consulta('INSERT INTO detalle_venta (id_venta, id_articulo, cantidad) VALUES (?, ?, ?)',
                         array($id, (int)$id_articulo, (int)$cantidad));
                flash('ok', 'Artículo agregado y stock descontado.');
                redirigir($aqui);
            } catch (PDOException $e) {
                $errores[] = mensaje_error_bd($e);
            }
        }
    }

    // 3. Quitar un artículo
    if ($accion === 'quitar') {
        $id_articulo = (int)post('id_articulo');

        if (!$editable) {
            flash('error', 'Solo se pueden quitar artículos de una venta Pendiente.');
            redirigir($aqui);
        }

        // El total nuevo no puede quedar por debajo de los pagos ya registrados
        $subtotal = (float)valor(
            'SELECT d.cantidad * a.precio FROM detalle_venta d JOIN articulo a ON a.id_articulo = d.id_articulo
             WHERE d.id_venta = ? AND d.id_articulo = ?',
            array($id, $id_articulo)
        );
        $total_nuevo  = $venta['total'] - $subtotal;
        $comprometido = pagos_comprometidos($id);
        if ($total_nuevo < $comprometido - 0.001) {
            flash('error', 'No se puede quitar el artículo: el total quedaría en ' . dinero($total_nuevo)
                . ' y la venta ya tiene pagos por ' . dinero($comprometido) . '. Elimine o rechace primero esos pagos.');
            redirigir($aqui);
        }

        try {
            en_transaccion(function () use ($id, $id_articulo) {
                $cantidad = valor('DELETE FROM detalle_venta WHERE id_venta = ? AND id_articulo = ? RETURNING cantidad',
                                  array($id, $id_articulo));
                if ($cantidad !== false) {
                    consulta('UPDATE articulo SET stock = stock + ? WHERE id_articulo = ?', array((int)$cantidad, $id_articulo));
                }
            });
            flash('ok', 'Artículo quitado y stock devuelto.');
        } catch (PDOException $e) {
            flash('error', mensaje_error_bd($e));
        }
        redirigir($aqui);
    }
}

// ---------- Datos para mostrar ----------
$lineas     = lineas_venta($id);
$pagos      = pagos_venta($id);
$articulos  = filas('SELECT id_articulo, nombre, precio, stock FROM articulo ORDER BY nombre');
$saldo      = $venta['total'] - $venta['pagado'];
$siguientes = $TRANSICIONES_VENTA[$venta['estado']];
$acepta_pago = in_array($venta['estado'], $VENTA_ACEPTA_PAGOS, true) && $venta['total'] > 0 && $saldo > 0;

$titulo = 'Venta #' . $id;
$activo = 'ventas.php';
include 'includes/encabezado.php';
?>

<div class="encabezado">
    <div>
        <h1>Venta #<?= $id ?> <?= etiqueta_estado($venta['estado']) ?></h1>
        <p class="subtitulo"><a href="ventas.php">← Volver a ventas</a></p>
    </div>
    <?= boton_post(array('accion' => 'eliminar', 'id_venta' => $id),
                   '¿Eliminar la venta #' . $id . '? Sus artículos vuelven al inventario.',
                   'Eliminar venta', 'boton peligro', 'ventas.php') ?>
</div>

<?php mostrar_errores($errores); ?>

<!-- Resumen -->
<section class="tarjeta">
    <div class="datos">
        <div>
            <div class="nombre">Cliente</div>
            <div class="valor"><?= h($venta['cliente']) ?></div>
            <div class="ayuda">Cédula <?= $venta['id_cliente'] ?> · <?= h($venta['ciudad']) ?></div>
        </div>
        <div>
            <div class="nombre">Fecha</div>
            <div class="valor"><?= fecha($venta['fecha']) ?></div>
        </div>
        <div>
            <div class="nombre">Dirección de envío</div>
            <div class="valor"><?= h($venta['direccion_envio']) ?></div>
        </div>
    </div>

    <div class="resumen">
        <div><div class="valor"><?= dinero($venta['total']) ?></div><div class="nombre">Total (calculado)</div></div>
        <div><div class="valor"><?= dinero($venta['pagado']) ?></div><div class="nombre">Pagado (aprobado)</div></div>
        <div><div class="valor <?= $saldo > 0 ? 'stock-bajo' : '' ?>"><?= dinero($saldo) ?></div><div class="nombre">Saldo</div></div>
    </div>
</section>

<!-- Artículos (detalle_venta) -->
<section class="tarjeta">
    <h2>Artículos</h2>

    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>Artículo</th>
                    <th class="num">Precio</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineas as $l): ?>
                    <tr>
                        <td><?= h($l['nombre']) ?></td>
                        <td class="num"><?= dinero($l['precio']) ?></td>
                        <td class="num"><?= $l['cantidad'] ?></td>
                        <td class="num"><?= dinero($l['subtotal']) ?></td>
                        <td class="acciones">
                            <?php if ($editable): ?>
                                <?= boton_post(array('accion' => 'quitar', 'id_articulo' => $l['id_articulo']), '¿Quitar ' . $l['nombre'] . ' de la venta?', 'Quitar') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$lineas): ?>
                    <tr><td colspan="5" class="vacio">La venta aún no tiene artículos.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="num"><?= dinero($venta['total']) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if ($editable): ?>
        <form method="post" class="en-linea" style="margin-top:16px">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="agregar">
            <div>
                <label for="id_articulo">Agregar artículo</label>
                <select id="id_articulo" name="id_articulo" required>
                    <option value="">Seleccione…</option>
                    <?php foreach ($articulos as $a): ?>
                        <option value="<?= $a['id_articulo'] ?>">
                            <?= h($a['nombre']) ?> — <?= dinero($a['precio']) ?> — <?= $a['stock'] > 0 ? 'stock ' . $a['stock'] : 'sin stock' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="angosto">
                <label for="cantidad">Cantidad</label>
                <input id="cantidad" name="cantidad" type="number" min="1" step="1" value="1" required>
            </div>
            <button class="boton">Agregar</button>
        </form>
        <p class="ayuda">El inventario lo controla la base de datos: si no hay stock suficiente, el disparador rechaza la línea.</p>
    <?php else: ?>
        <p class="ayuda">La venta está en estado <?= h($venta['estado']) ?>: ya no se pueden cambiar sus artículos.</p>
    <?php endif; ?>
</section>

<!-- Pagos (forma_pago) -->
<section class="tarjeta">
    <div class="encabezado" style="margin-bottom:10px">
        <h2 style="margin:0">Pagos</h2>
        <?php if ($acepta_pago): ?>
            <a class="boton chico" href="pagos.php?accion=nuevo&venta=<?= $id ?>">+ Registrar pago</a>
        <?php endif; ?>
    </div>

    <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Medio</th>
                    <th>Estado</th>
                    <th class="num">Monto</th>
                    <th></th>
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
                        <td class="acciones">
                            <a class="boton secundario chico" href="pagos.php?accion=editar&id=<?= $p['id_pago'] ?>&volver=venta">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$pagos): ?>
                    <tr><td colspan="6" class="vacio">Sin pagos registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Cambiar estado / dirección -->
<section class="tarjeta">
    <h2>Actualizar seguimiento</h2>

    <form method="post" class="formulario">
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="actualizar">

        <div>
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <?= opciones_simples(array_merge(array($venta['estado']), $siguientes), $venta['estado']) ?>
            </select>
            <?php if (!$siguientes): ?>
                <div class="ayuda">Estado final: ya no cambia.</div>
            <?php endif; ?>
        </div>

        <div>
            <label for="direccion_envio">Dirección de envío</label>
            <input id="direccion_envio" name="direccion_envio" maxlength="150" required value="<?= h($venta['direccion_envio']) ?>">
        </div>

        <div class="completo botones">
            <button class="boton">Guardar cambios</button>
        </div>
    </form>
</section>

<?php include 'includes/pie.php'; ?>
