<?php

require_once 'includes/funciones.php';
require_cliente();

if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
    $_SESSION['carrito'] = array();
}

$cliente = usuario_actual();
$errores = array();

$direccion_envio = $cliente ? $cliente['direccion'] : '';
$tipo_pago       = 'PSE';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        flash('error', 'La sesión del formulario expiró. Intente nuevamente.');
        redirigir('carrito.php');
    }

    $accion = post('accion');

    if ($accion === 'agregar') {
        $id_articulo = (int)post('id_articulo');
        $cantidad_txt = post('cantidad', '1');

        validar_entero($cantidad_txt, 'Cantidad', $errores, 1, 100000);

        $art = fila(
            'SELECT id_articulo, nombre, stock FROM articulo WHERE id_articulo = ?',
            array($id_articulo)
        );

        if (!$art || (int)$art['stock'] <= 0) {
            flash('error', 'El artículo seleccionado no tiene stock disponible.');
            redirigir('tienda.php');
        }

        if ($errores) {
            flash('error', implode(' ', $errores));
            redirigir('articulo.php?id=' . $id_articulo);
        }

        $actual = isset($_SESSION['carrito'][$id_articulo]) ? (int)$_SESSION['carrito'][$id_articulo] : 0;
        $nueva  = $actual + (int)$cantidad_txt;

        if ($nueva > (int)$art['stock']) {
            flash(
                'error',
                'Stock insuficiente para ' . $art['nombre'] . ': disponible ' . $art['stock'] . ', en carrito ' . $actual . '.'
            );
            redirigir('articulo.php?id=' . $id_articulo);
        }

        $_SESSION['carrito'][$id_articulo] = $nueva;

        $volver = post('volver');
        if (!preg_match('/^(tienda\.php(\?[A-Za-z0-9_=&%+.\-]*)?|articulo\.php\?id=\d+)$/', $volver)) {
            flash('ok', $art['nombre'] . ' agregado al carrito.');
            redirigir('carrito.php');
        }

        // La página de origen muestra la ventanita "¿Quieres ir al carrito?" (encabezado_tienda.php).
        $_SESSION['aviso_carrito'] = $art['nombre'];
        redirigir($volver);
    }

    if ($accion === 'actualizar_linea') {
        $id_articulo  = (int)post('id_articulo');
        $cantidad_txt = post('cantidad');

        if ($cantidad_txt === '0') {
            unset($_SESSION['carrito'][$id_articulo]);
            flash('info', 'Artículo quitado del carrito.');
            redirigir('carrito.php');
        }

        validar_entero($cantidad_txt, 'Cantidad', $errores, 1, 100000);
        if ($errores) {
            flash('error', implode(' ', $errores));
            redirigir('carrito.php');
        }

        $art = fila(
            'SELECT id_articulo, nombre, stock FROM articulo WHERE id_articulo = ?',
            array($id_articulo)
        );

        if (!$art || (int)$art['stock'] <= 0) {
            unset($_SESSION['carrito'][$id_articulo]);
            flash('error', 'El artículo ya no está disponible y fue retirado del carrito.');
            redirigir('carrito.php');
        }

        if ((int)$cantidad_txt > (int)$art['stock']) {
            flash('error', 'Stock insuficiente para ' . $art['nombre'] . ': máximo ' . $art['stock'] . ' unidades.');
            redirigir('carrito.php');
        }

        $_SESSION['carrito'][$id_articulo] = (int)$cantidad_txt;
        flash('ok', 'Cantidad actualizada.');
        redirigir('carrito.php');
    }

    if ($accion === 'quitar') {
        $id_articulo = (int)post('id_articulo');
        unset($_SESSION['carrito'][$id_articulo]);
        flash('info', 'Artículo quitado del carrito.');
        redirigir('carrito.php');
    }

    if ($accion === 'vaciar') {
        $_SESSION['carrito'] = array();
        flash('info', 'Carrito vaciado.');
        redirigir('carrito.php');
    }

    if ($accion === 'confirmar') {
        $direccion_envio = post('direccion_envio');
        $tipo_pago       = post('tipo_pago');

        if ($direccion_envio === '' && $cliente) {
            $direccion_envio = $cliente['direccion'];
        }

        if (empty($_SESSION['carrito'])) {
            $errores[] = 'Tu carrito está vacío.';
        }

        validar_texto($direccion_envio, 'Dirección de envío', 150, $errores);
        validar_opcion($tipo_pago, 'Medio de pago', $TIPOS_PAGO, $errores);

        if (!$errores) {
            $pdo = db();
            try {
                $pdo->beginTransaction();

                $hoy      = date('Y-m-d');
                $id_venta = (int)valor(
                    "INSERT INTO venta (fecha, estado, direccion_envio, id_cliente)
                     VALUES (?, 'Pendiente', ?, ?)
                     RETURNING id_venta",
                    array($hoy, $direccion_envio, (int)$_SESSION['id_cliente'])
                );

                foreach ($_SESSION['carrito'] as $id_art => $cant) {
                    consulta(
                        'INSERT INTO detalle_venta (id_venta, id_articulo, cantidad) VALUES (?, ?, ?)',
                        array($id_venta, (int)$id_art, (int)$cant)
                    );
                }

                $venta_creada = fila(sql_ventas() . ' WHERE v.id_venta = ?', array($id_venta));
                $total_venta  = $venta_creada ? (float)$venta_creada['total'] : 0.0;

                consulta(
                    "INSERT INTO forma_pago (tipo, monto, fecha_pago, estado, id_venta)
                     VALUES (?, ?, ?, 'Pendiente', ?)",
                    array($tipo_pago, $total_venta, $hoy, $id_venta)
                );

                $pdo->commit();
                $_SESSION['carrito'] = array();

                flash(
                    'ok',
                    '¡Compra #' . $id_venta . ' confirmada por ' . dinero($total_venta)
                    . '! El pago por ' . $tipo_pago . ' quedó registrado en estado Pendiente.'
                );
                redirigir('mis_compras.php?id=' . $id_venta);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', mensaje_error_bd($e));
                redirigir('carrito.php');
            }
        }
    }
}

$lineas_carrito = array();
$total_carrito  = 0.0;

foreach ($_SESSION['carrito'] as $id_art => $cant) {
    $art = fila(
        'SELECT a.id_articulo, a.nombre, a.precio, a.stock, c.nombre AS categoria,
                ai.actualizada AS imagen_version
         FROM articulo a
         JOIN categoria c ON c.id_categoria = a.id_categoria
         LEFT JOIN articulo_imagen ai ON ai.id_articulo = a.id_articulo
         WHERE a.id_articulo = ?',
        array((int)$id_art)
    );

    if (!$art) {
        unset($_SESSION['carrito'][$id_art]);
        continue;
    }

    $subtotal       = (float)$art['precio'] * (int)$cant;
    $total_carrito += $subtotal;

    $lineas_carrito[] = array(
        'id_articulo' => (int)$art['id_articulo'],
        'nombre'      => $art['nombre'],
        'categoria'   => $art['categoria'],
        'imagen'      => $art['imagen_version'],
        'precio'      => (float)$art['precio'],
        'stock'       => (int)$art['stock'],
        'cantidad'    => (int)$cant,
        'subtotal'    => $subtotal,
    );
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
