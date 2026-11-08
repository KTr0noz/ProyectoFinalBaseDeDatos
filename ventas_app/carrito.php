<?php
/**
 * Carrito de compras del cliente.
 *   - El carrito vive en la sesión: $_SESSION['carrito'] = array(id_articulo => cantidad).
 *     No toca la base hasta confirmar el pedido.
 *   - Confirmar crea, en UNA transacción, la venta Pendiente, sus líneas (el disparador
 *     descuenta el stock) y un pago Pendiente por el total. Si algo falla, no queda nada a medias.
 */

require_once 'includes/funciones.php';
require_cliente();

$_SESSION['carrito'] = is_array($_SESSION['carrito'] ?? null) ? $_SESSION['carrito'] : array();
$carrito = &$_SESSION['carrito'];
$cliente = usuario_actual();
$errores = array();

$direccion_envio = $cliente['direccion'];
$tipo_pago       = 'PSE';

/** Artículo con su stock, o false si no existe. */
function articulo_para_carrito($id_articulo)
{
    return fila('SELECT id_articulo, nombre, stock FROM articulo WHERE id_articulo = ?', array((int)$id_articulo));
}

// ---------- Acciones (todas por POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('carrito.php');
    $accion      = post('accion');
    $id_articulo = (int)post('id_articulo');

    // 1. Agregar desde el catálogo o la ficha. Vuelve a esa página con la ventanita "¿Quieres ir al carrito?".
    if ($accion === 'agregar') {
        $cantidad = post('cantidad', '1');
        validar_entero($cantidad, 'Cantidad', $errores, 1, 100000);

        $art = articulo_para_carrito($id_articulo);
        if (!$art || $art['stock'] <= 0) {
            flash('error', 'El artículo seleccionado no tiene stock disponible.');
            redirigir('tienda.php');
        }
        if ($errores) {
            flash('error', implode(' ', $errores));
            redirigir('articulo.php?id=' . $id_articulo);
        }

        $en_carrito = (int)($carrito[$id_articulo] ?? 0);
        if ($en_carrito + (int)$cantidad > $art['stock']) {
            flash('error', 'Stock insuficiente para ' . $art['nombre'] . ': disponible ' . $art['stock'] . ', en carrito ' . $en_carrito . '.');
            redirigir('articulo.php?id=' . $id_articulo);
        }
        $carrito[$id_articulo] = $en_carrito + (int)$cantidad;

        // Solo se vuelve al catálogo o a una ficha (nunca a una dirección cualquiera que llegue en el formulario)
        $volver = post('volver');
        if (preg_match('/^(tienda\.php(\?[A-Za-z0-9_=&%+.\-]*)?|articulo\.php\?id=\d+)$/', $volver)) {
            $_SESSION['aviso_carrito'] = $art['nombre'];
            redirigir($volver);
        }
        flash('ok', $art['nombre'] . ' agregado al carrito.');
        redirigir('carrito.php');
    }

    // 2. Cambiar la cantidad de una línea (0 = quitarla)
    if ($accion === 'actualizar_linea') {
        $cantidad = post('cantidad');
        if ($cantidad === '0') {
            unset($carrito[$id_articulo]);
            flash('info', 'Artículo quitado del carrito.');
            redirigir('carrito.php');
        }

        validar_entero($cantidad, 'Cantidad', $errores, 1, 100000);
        $art = $errores ? null : articulo_para_carrito($id_articulo);

        if ($errores) {
            flash('error', implode(' ', $errores));
        } elseif (!$art || $art['stock'] <= 0) {
            unset($carrito[$id_articulo]);
            flash('error', 'El artículo ya no está disponible y fue retirado del carrito.');
        } elseif ((int)$cantidad > $art['stock']) {
            flash('error', 'Stock insuficiente para ' . $art['nombre'] . ': máximo ' . $art['stock'] . ' unidades.');
        } else {
            $carrito[$id_articulo] = (int)$cantidad;
            flash('ok', 'Cantidad actualizada.');
        }
        redirigir('carrito.php');
    }

    // 3. Quitar una línea
    if ($accion === 'quitar') {
        unset($carrito[$id_articulo]);
        flash('info', 'Artículo quitado del carrito.');
        redirigir('carrito.php');
    }

    // 4. Vaciar (después de "¿Estás seguro…?")
    if ($accion === 'vaciar') {
        $carrito = array();
        flash('info', 'Carrito vaciado.');
        redirigir('carrito.php');
    }

    // 5. Confirmar el pedido
    if ($accion === 'confirmar') {
        $direccion_envio = post('direccion_envio') !== '' ? post('direccion_envio') : $cliente['direccion'];
        $tipo_pago       = post('tipo_pago');

        if (!$carrito) {
            $errores[] = 'Tu carrito está vacío.';
        }
        validar_texto($direccion_envio, 'Dirección de envío', 150, $errores);
        validar_opcion($tipo_pago, 'Medio de pago', $TIPOS_PAGO, $errores);

        if (!$errores) {
            try {
                $venta = en_transaccion(function () use ($carrito, $direccion_envio, $tipo_pago) {
                    $hoy = date('Y-m-d');
                    $id_venta = (int)valor(
                        "INSERT INTO venta (fecha, estado, direccion_envio, id_cliente) VALUES (?, 'Pendiente', ?, ?) RETURNING id_venta",
                        array($hoy, $direccion_envio, (int)$_SESSION['id_cliente'])
                    );
                    foreach ($carrito as $id_art => $cant) {
                        // Si no hay stock, el disparador lanza un error y se deshace todo
                        consulta('INSERT INTO detalle_venta (id_venta, id_articulo, cantidad) VALUES (?, ?, ?)',
                                 array($id_venta, (int)$id_art, (int)$cant));
                    }
                    $venta = buscar_venta($id_venta);
                    consulta("INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta) VALUES (?, ?, ?, 'Pendiente', ?)",
                             array($tipo_pago, $venta['total'], $hoy, $id_venta));
                    return $venta;
                });

                $carrito = array();
                flash('ok', '¡Compra #' . $venta['id_venta'] . ' confirmada por ' . dinero($venta['total'])
                    . '! El pago por ' . $tipo_pago . ' quedó registrado en estado Pendiente.');
                redirigir('mis_compras.php?id=' . $venta['id_venta']);
            } catch (PDOException $e) {
                flash('error', mensaje_error_bd($e));
                redirigir('carrito.php');
            }
        }
    }
}

// ---------- Líneas del carrito con precios actuales ----------
$lineas_carrito = array();
$total_carrito  = 0.0;
foreach ($carrito as $id_art => $cant) {
    $art = fila(
        'SELECT a.id_articulo, a.nombre, a.precio, a.stock, c.nombre AS categoria, ai.actualizada AS imagen
         FROM articulo a
         JOIN categoria c ON c.id_categoria = a.id_categoria
         LEFT JOIN articulo_imagen ai ON ai.id_articulo = a.id_articulo
         WHERE a.id_articulo = ?',
        array((int)$id_art)
    );
    if (!$art) { // el artículo ya no existe
        unset($carrito[$id_art]);
        continue;
    }
    $art['cantidad']  = (int)$cant;
    $art['subtotal']  = (float)$art['precio'] * (int)$cant;
    $total_carrito   += $art['subtotal'];
    $lineas_carrito[] = $art;
}

$titulo = 'Carrito de compras';
$activo = 'carrito.php';
include 'includes/encabezado_tienda.php';
?>

<div class="encabezado">
    <div>
        <h1>Carrito de compras</h1>
        <p class="subtitulo">Revisa las unidades y confirma tu pedido.</p>
    </div>
    <?php if ($lineas_carrito): ?>
        <button type="button" class="boton secundario chico" data-abrir-dialogo="dialogo-vaciar">Vaciar carrito</button>

        <dialog class="dialogo-confirmar" id="dialogo-vaciar" aria-labelledby="dialogo-vaciar-titulo">
            <p id="dialogo-vaciar-titulo">¿Estás seguro que quieres vaciar el carrito?</p>
            <div class="dialogo-botones">
                <form method="dialog">
                    <button class="boton secundario">No</button>
                </form>
                <form method="post" action="carrito.php">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="vaciar">
                    <button class="boton peligro">Sí, vaciar</button>
                </form>
            </div>
        </dialog>
    <?php endif; ?>
</div>

<?php mostrar_errores($errores); ?>

<?php if ($lineas_carrito): ?>
    <div class="columnas">
        <section class="tarjeta">
            <h2>Artículos seleccionados</h2>
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
                        <?php foreach ($lineas_carrito as $l): ?>
                            <tr>
                                <td>
                                    <div class="celda-articulo">
                                        <?= imagen_articulo($l['id_articulo'], $l['imagen'], $l['nombre'], 'imagen-articulo imagen-mini') ?>
                                        <div>
                                            <a href="articulo.php?id=<?= $l['id_articulo'] ?>"><?= h($l['nombre']) ?></a>
                                            <div class="ayuda">
                                                <?= h($l['categoria']) ?> ·
                                                <?php if ($l['stock'] < $l['cantidad']): ?>
                                                    <span class="stock-bajo">Disponible: <?= $l['stock'] ?></span>
                                                <?php else: ?>
                                                    stock <?= $l['stock'] ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="num"><?= dinero($l['precio']) ?></td>
                                <td class="num">
                                    <form method="post" class="form-cantidad-carrito">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="actualizar_linea">
                                        <input type="hidden" name="id_articulo" value="<?= $l['id_articulo'] ?>">
                                        <input
                                            name="cantidad"
                                            type="number"
                                            min="1"
                                            max="<?= max($l['stock'], 1) ?>"
                                            value="<?= $l['cantidad'] ?>"
                                            aria-label="<?= h('Cantidad de ' . $l['nombre']) ?>"
                                        >
                                        <button class="boton secundario chico">Actualizar</button>
                                    </form>
                                </td>
                                <td class="num"><?= dinero($l['subtotal']) ?></td>
                                <td class="acciones">
                                    <form method="post">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="accion" value="quitar">
                                        <input type="hidden" name="id_articulo" value="<?= $l['id_articulo'] ?>">
                                        <button class="boton peligro chico">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3">Total a pagar</td>
                            <td class="num"><?= dinero($total_carrito) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <section class="tarjeta panel-confirmar">
            <h2>Confirmar compra</h2>
            <form method="post" class="formulario-confirmar">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="confirmar">

                <div>
                    <label for="direccion_envio">Dirección de envío</label>
                    <input
                        id="direccion_envio"
                        name="direccion_envio"
                        maxlength="150"
                        required
                        value="<?= h($direccion_envio) ?>"
                    >
                    <div class="ayuda">Por defecto usamos la dirección registrada en tu cuenta.</div>
                </div>

                <div>
                    <label for="tipo_pago">Medio de pago</label>
                    <select id="tipo_pago" name="tipo_pago" required>
                        <?= opciones_simples($TIPOS_PAGO, $tipo_pago) ?>
                    </select>
                    <div class="ayuda">El pago se registrará por <?= dinero($total_carrito) ?> en estado Pendiente para verificación.</div>
                </div>

                <div class="resumen-compra">
                    <span>Total del pedido</span>
                    <strong><?= dinero($total_carrito) ?></strong>
                </div>

                <button class="boton boton-completo">Confirmar pedido</button>
            </form>
        </section>
    </div>
<?php else: ?>
    <div class="vacio-tienda">
        <p>Tu carrito está vacío.</p>
        <a class="boton" href="tienda.php">Explorar catálogo</a>
    </div>
<?php endif; ?>

<?php include 'includes/pie.php'; ?>
