<?php
/**
 * Clientes (tabla cliente): listar, buscar, crear, editar y eliminar.
 *   - La cédula es la llave primaria. Si se cambia, sus ventas la siguen (ON UPDATE CASCADE).
 *   - La base no deja repetir cédula ni correo, ni borrar un cliente con ventas (ON DELETE RESTRICT).
 *   - Las cuentas de administrador no se pueden borrar desde aquí.
 */

require_once 'includes/funciones.php';
require_admin();

$accion  = get('accion');
$errores = array();
$datos   = array('original' => '', 'id_cliente' => '', 'nombre' => '', 'apellido' => '',
                 'email' => '', 'telefono' => '', 'direccion' => '', 'id_ciudad' => '');

// ---------- Formularios enviados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('clientes.php');

    if (post('accion') === 'eliminar') {
        $id = (int)post('id_cliente');
        if (valor('SELECT rol FROM cliente WHERE id_cliente = ?', array($id)) === 'admin') {
            flash('error', 'No se puede eliminar una cuenta de administrador.');
            redirigir('clientes.php');
        }
        eliminar_y_volver('DELETE FROM cliente WHERE id_cliente = ?', $id, 'Cliente eliminado.', 'clientes.php');
    }

    // Guardar. "original" es la cédula antes de editar (vacía si es un cliente nuevo).
    $datos = datos_cliente_del_formulario() + array('original' => post('original'));
    validar_datos_cliente($datos, $errores);

    if (!$errores) {
        $params = array((int)$datos['id_cliente'], $datos['nombre'], $datos['apellido'], $datos['email'],
                        $datos['telefono'], $datos['direccion'], (int)$datos['id_ciudad']);
        try {
            if ($datos['original'] === '') {
                consulta('INSERT INTO cliente (id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad)
                          VALUES (?, ?, ?, ?, ?, ?, ?)', $params);
                flash('ok', 'Cliente registrado.');
            } else {
                $params[] = (int)$datos['original'];
                consulta('UPDATE cliente
                          SET id_cliente = ?, nombre = ?, apellido = ?, email = ?, telefono = ?, direccion = ?, id_ciudad = ?
                          WHERE id_cliente = ?', $params);
                flash('ok', 'Cliente actualizado.');
            }
            redirigir('clientes.php');
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
    $accion = 'formulario';
}

if ($accion === 'editar') {
    $datos = buscar_o_volver(
        'SELECT id_cliente, nombre, apellido, email, telefono, direccion, id_ciudad FROM cliente WHERE id_cliente = ?',
        (int)get('id'), 'El cliente no existe.', 'clientes.php'
    );
    $datos['original'] = $datos['id_cliente'];
    $accion = 'formulario';
}
if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$titulo = 'Clientes';
$activo = 'clientes.php';
include 'includes/encabezado.php';

// ---------- Formulario de nuevo / editar ----------
if ($accion === 'formulario'):
    $ciudades = lista_ciudades();
?>
    <div class="encabezado">
        <h1><?= $datos['original'] === '' ? 'Nuevo cliente' : 'Editar cliente' ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if (!$ciudades): ?>
        <div class="alerta alerta-info">Primero registre al menos una <a href="ciudades.php?accion=nueva">ciudad</a>.</div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario">
        <?= csrf_campo() ?>
        <input type="hidden" name="original" value="<?= h($datos['original']) ?>">

        <div>
            <label for="id_cliente">Cédula</label>
            <input id="id_cliente" name="id_cliente" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" required value="<?= h($datos['id_cliente']) ?>">
            <div class="ayuda">Solo números, mayor que 0.</div>
        </div>

        <div>
            <label for="id_ciudad">Ciudad</label>
            <select id="id_ciudad" name="id_ciudad" required>
                <option value="">Seleccione…</option>
                <?= opciones($ciudades, $datos['id_ciudad']) ?>
            </select>
        </div>

        <div>
            <label for="nombre">Nombres</label>
            <input id="nombre" name="nombre" maxlength="80" required value="<?= h($datos['nombre']) ?>">
        </div>

        <div>
            <label for="apellido">Apellidos</label>
            <input id="apellido" name="apellido" maxlength="80" required value="<?= h($datos['apellido']) ?>">
        </div>

        <div>
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" maxlength="120" required value="<?= h($datos['email']) ?>">
        </div>

        <div>
            <label for="telefono">Teléfono</label>
            <input id="telefono" name="telefono" maxlength="20" required value="<?= h($datos['telefono']) ?>">
        </div>

        <div class="completo">
            <label for="direccion">Dirección</label>
            <input id="direccion" name="direccion" maxlength="150" required value="<?= h($datos['direccion']) ?>">
        </div>

        <div class="completo botones">
            <button class="boton">Guardar</button>
            <a class="boton secundario" href="clientes.php">Cancelar</a>
        </div>
    </form>

<?php else:
    // ---------- Lista con búsqueda por nombre, correo o cédula ----------
    $q = get('q');
    $lista = filas(
        "SELECT c.id_cliente, c.nombre, c.apellido, c.email, c.telefono, c.direccion, c.rol,
                ci.nombre AS ciudad,
                (SELECT COUNT(*) FROM venta v WHERE v.id_cliente = c.id_cliente) AS ventas
         FROM cliente c
         JOIN ciudad ci ON ci.id_ciudad = c.id_ciudad
         WHERE c.nombre || ' ' || c.apellido ILIKE ? OR c.email ILIKE ? OR CAST(c.id_cliente AS TEXT) LIKE ?
         ORDER BY c.apellido, c.nombre",
        array("%$q%", "%$q%", "%$q%")
    );
?>
    <div class="encabezado">
        <div>
            <h1>Clientes</h1>
            <p class="subtitulo">Compradores registrados en el portal.</p>
        </div>
        <a class="boton" href="clientes.php?accion=nuevo">+ Nuevo cliente</a>
    </div>

    <div class="tarjeta">
        <form class="filtros">
            <input name="q" placeholder="Buscar por nombre, cédula o correo" value="<?= h($q) ?>">
            <button class="boton secundario">Buscar</button>
        </form>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Ciudad</th>
                        <th class="num">Ventas</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $c): $nombre = $c['nombre'] . ' ' . $c['apellido']; ?>
                        <tr>
                            <td><?= $c['id_cliente'] ?></td>
                            <td><?= h($nombre) ?><div class="ayuda"><?= h($c['direccion']) ?></div></td>
                            <td><?= h($c['email']) ?><div class="ayuda"><?= h($c['telefono']) ?></div></td>
                            <td><?= h($c['ciudad']) ?></td>
                            <td class="num"><a href="ventas.php?q=<?= $c['id_cliente'] ?>"><?= $c['ventas'] ?></a></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="clientes.php?accion=editar&id=<?= $c['id_cliente'] ?>">Editar</a>
                                <?php if ($c['rol'] !== 'admin'): ?>
                                    <?= boton_post(array('accion' => 'eliminar', 'id_cliente' => $c['id_cliente']), '¿Eliminar al cliente ' . $nombre . '?') ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr><td colspan="6" class="vacio">No hay clientes que coincidan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
