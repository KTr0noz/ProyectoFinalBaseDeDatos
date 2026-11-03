<?php
/*
 * Clientes: listar, buscar, crear, editar y eliminar.
 * - La cédula es la llave primaria (pk_cliente) y debe ser > 0 (ck_cliente_id).
 * - El correo es único (uq_cliente_email).
 * - Si se corrige la cédula, sus ventas se actualizan solas (fk_venta_cliente ON UPDATE CASCADE).
 * - No se puede borrar un cliente con ventas (fk_venta_cliente ON DELETE RESTRICT).
 */
require_once 'includes/funciones.php';

$accion  = get('accion');
$errores = array();
$datos   = array('original' => '', 'id_cliente' => '', 'nombre' => '', 'apellido' => '', 'email' => '',
                 'telefono' => '', 'direccion' => '', 'id_ciudad' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('accion') === 'eliminar') {
        try {
            consulta('DELETE FROM cliente WHERE id_cliente = ?', array((int)post('id_cliente')));
            flash('ok', 'Cliente eliminado.');
        } catch (PDOException $e) {
            flash('error', mensaje_error_bd($e));
        }
        redirigir('clientes.php');
    }

    $datos = array(
        'original'   => post('original'),
        'id_cliente' => post('id_cliente'),
        'nombre'     => post('nombre'),
        'apellido'   => post('apellido'),
        'email'      => mb_strtolower(post('email'), 'UTF-8'),
        'telefono'   => post('telefono'),
        'direccion'  => post('direccion'),
        'id_ciudad'  => post('id_ciudad'),
    );
    validar_entero($datos['id_cliente'], 'Cédula', $errores);
    validar_texto($datos['nombre'], 'Nombre', 80, $errores);
    validar_texto($datos['apellido'], 'Apellido', 80, $errores);
    validar_texto($datos['email'], 'Correo', 120, $errores);
    validar_email($datos['email'], 'Correo', $errores);
    validar_texto($datos['telefono'], 'Teléfono', 20, $errores);
    validar_patron($datos['telefono'], 'Teléfono', '/^[0-9+() -]{7,20}$/', 'solo números, espacios, +, ( ) o guiones (mínimo 7).', $errores);
    validar_texto($datos['direccion'], 'Dirección', 150, $errores);
    if (!fila('SELECT 1 FROM ciudad WHERE id_ciudad = ?', array((int)$datos['id_ciudad']))) {
        $errores[] = 'Seleccione una ciudad.';
    }

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
                consulta('UPDATE cliente SET id_cliente = ?, nombre = ?, apellido = ?, email = ?, telefono = ?,
                          direccion = ?, id_ciudad = ? WHERE id_cliente = ?', $params);
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
    $datos = fila('SELECT * FROM cliente WHERE id_cliente = ?', array((int)get('id')));
    if (!$datos) {
        flash('error', 'El cliente no existe.');
        redirigir('clientes.php');
    }
    $datos['original'] = $datos['id_cliente'];
    $accion = 'formulario';
}
if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$titulo = 'Clientes';
$activo = 'clientes.php';
include 'includes/encabezado.php';

if ($accion === 'formulario'):
    $ciudades = array();
    foreach (filas('SELECT id_ciudad, nombre, departamento FROM ciudad ORDER BY nombre') as $c) {
        $ciudades[$c['id_ciudad']] = $c['nombre'] . ' (' . $c['departamento'] . ')';
    }
    ?>
    <div class="encabezado">
        <h1><?= $datos['original'] === '' ? 'Nuevo cliente' : 'Editar cliente' ?></h1>
    </div>
    <?php mostrar_errores($errores); ?>
    <?php if (!$ciudades): ?>
        <div class="alerta alerta-info">Primero registre al menos una <a href="ciudades.php?accion=nueva">ciudad</a>.</div>
    <?php endif; ?>
    <form method="post" class="tarjeta formulario">
        <input type="hidden" name="accion" value="guardar">
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
    $q = get('q');
    $lista = filas(
        "SELECT c.*, ci.nombre AS ciudad,
                (SELECT COUNT(*) FROM venta v WHERE v.id_cliente = c.id_cliente) AS ventas
         FROM cliente c JOIN ciudad ci ON ci.id_ciudad = c.id_ciudad
         WHERE c.nombre || ' ' || c.apellido ILIKE ? OR c.email ILIKE ? OR CAST(c.id_cliente AS TEXT) LIKE ?
         ORDER BY c.apellido, c.nombre",
        array("%$q%", "%$q%", "%$q%")
    );
    ?>
    <div class="encabezado">
        <div><h1>Clientes</h1><p class="subtitulo">Compradores registrados en el portal.</p></div>
        <a class="boton" href="clientes.php?accion=nuevo">+ Nuevo cliente</a>
    </div>
    <div class="tarjeta">
        <form class="filtros">
            <input name="q" placeholder="Buscar por nombre, cédula o correo" value="<?= h($q) ?>">
            <button class="boton secundario">Buscar</button>
        </form>
        <div class="tabla-contenedor">
        <table>
            <thead><tr><th>Cédula</th><th>Cliente</th><th>Contacto</th><th>Ciudad</th><th class="num">Ventas</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $c): ?>
                <tr>
                    <td><?= $c['id_cliente'] ?></td>
                    <td><?= h($c['nombre'] . ' ' . $c['apellido']) ?><div class="ayuda"><?= h($c['direccion']) ?></div></td>
                    <td><?= h($c['email']) ?><div class="ayuda"><?= h($c['telefono']) ?></div></td>
                    <td><?= h($c['ciudad']) ?></td>
                    <td class="num"><a href="ventas.php?q=<?= $c['id_cliente'] ?>"><?= $c['ventas'] ?></a></td>
                    <td class="acciones">
                        <a class="boton secundario chico" href="clientes.php?accion=editar&id=<?= $c['id_cliente'] ?>">Editar</a>
                        <form method="post" onsubmit="return confirm(<?= js('¿Eliminar al cliente ' . $c['nombre'] . ' ' . $c['apellido'] . '?') ?>)">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                            <button class="boton peligro chico">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lista): ?><tr><td colspan="6" class="vacio">No hay clientes que coincidan.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif;
include 'includes/pie.php';
