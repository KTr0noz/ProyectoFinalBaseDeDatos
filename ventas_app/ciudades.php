<?php
/**
 * Ciudades de los clientes (tabla ciudad): listar, buscar, crear, editar y eliminar.
 * La base no deja borrar una ciudad que tenga clientes (fk_cliente_ciudad ON DELETE RESTRICT).
 */

require_once 'includes/funciones.php';
require_admin();

$accion  = get('accion');
$errores = array();
$datos   = array('id_ciudad' => '', 'nombre' => '', 'departamento' => '', 'pais' => 'Colombia', 'codigo_postal' => '');

// ---------- Formularios enviados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('ciudades.php');

    if (post('accion') === 'eliminar') {
        eliminar_y_volver('DELETE FROM ciudad WHERE id_ciudad = ?', (int)post('id_ciudad'), 'Ciudad eliminada.', 'ciudades.php');
    }

    // Guardar: sin id es nueva, con id es edición
    $datos = array(
        'id_ciudad'     => post('id_ciudad'),
        'nombre'        => post('nombre'),
        'departamento'  => post('departamento'),
        'pais'          => post('pais'),
        'codigo_postal' => post('codigo_postal'),
    );
    validar_texto($datos['nombre'], 'Nombre', 80, $errores);
    validar_texto($datos['departamento'], 'Departamento', 80, $errores);
    validar_texto($datos['pais'], 'País', 60, $errores);
    validar_texto($datos['codigo_postal'], 'Código postal', 15, $errores);
    validar_patron($datos['codigo_postal'], 'Código postal', '/^[0-9A-Za-z-]{3,15}$/',
        'solo números, letras o guiones (3 a 15 caracteres).', $errores);

    if (!$errores) {
        try {
            $params = array($datos['nombre'], $datos['departamento'], $datos['pais'], $datos['codigo_postal']);
            if ($datos['id_ciudad'] === '') {
                consulta('INSERT INTO ciudad (nombre, departamento, pais, codigo_postal) VALUES (?, ?, ?, ?)', $params);
                flash('ok', 'Ciudad registrada.');
            } else {
                $params[] = (int)$datos['id_ciudad'];
                consulta('UPDATE ciudad SET nombre = ?, departamento = ?, pais = ?, codigo_postal = ? WHERE id_ciudad = ?', $params);
                flash('ok', 'Ciudad actualizada.');
            }
            redirigir('ciudades.php');
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
    $accion = 'formulario';
}

if ($accion === 'editar') {
    $datos  = buscar_o_volver('SELECT * FROM ciudad WHERE id_ciudad = ?', (int)get('id'), 'La ciudad no existe.', 'ciudades.php');
    $accion = 'formulario';
}
if ($accion === 'nueva') {
    $accion = 'formulario';
}

$titulo = 'Ciudades';
$activo = 'ciudades.php';
include 'includes/encabezado.php';

// ---------- Formulario de nueva / editar ----------
if ($accion === 'formulario'): ?>
    <div class="encabezado">
        <h1><?= $datos['id_ciudad'] === '' ? 'Nueva ciudad' : 'Editar ciudad' ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="tarjeta formulario">
        <?= csrf_campo() ?>
        <input type="hidden" name="id_ciudad" value="<?= h($datos['id_ciudad']) ?>">

        <div>
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="80" required value="<?= h($datos['nombre']) ?>">
        </div>

        <div>
            <label for="departamento">Departamento</label>
            <input id="departamento" name="departamento" maxlength="80" required value="<?= h($datos['departamento']) ?>">
        </div>

        <div>
            <label for="pais">País</label>
            <input id="pais" name="pais" maxlength="60" required value="<?= h($datos['pais']) ?>">
        </div>

        <div>
            <label for="codigo_postal">Código postal</label>
            <input id="codigo_postal" name="codigo_postal" maxlength="15" required pattern="[0-9A-Za-z\-]{3,15}" value="<?= h($datos['codigo_postal']) ?>">
        </div>

        <div class="completo botones">
            <button class="boton">Guardar</button>
            <a class="boton secundario" href="ciudades.php">Cancelar</a>
        </div>
    </form>

<?php else:
    // ---------- Lista con búsqueda ----------
    $q = get('q');
    $lista = filas(
        'SELECT ci.*, (SELECT COUNT(*) FROM cliente c WHERE c.id_ciudad = ci.id_ciudad) AS clientes
         FROM ciudad ci
         WHERE ci.nombre ILIKE ? OR ci.departamento ILIKE ?
         ORDER BY ci.nombre',
        array("%$q%", "%$q%")
    );
?>
    <div class="encabezado">
        <div>
            <h1>Ciudades</h1>
            <p class="subtitulo">Ciudades donde residen los clientes.</p>
        </div>
        <a class="boton" href="ciudades.php?accion=nueva">+ Nueva ciudad</a>
    </div>

    <div class="tarjeta">
        <form class="filtros">
            <input name="q" placeholder="Buscar por ciudad o departamento" value="<?= h($q) ?>">
            <button class="boton secundario">Buscar</button>
        </form>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ciudad</th>
                        <th>Departamento</th>
                        <th>País</th>
                        <th>Código postal</th>
                        <th class="num">Clientes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $c): ?>
                        <tr>
                            <td><?= $c['id_ciudad'] ?></td>
                            <td><?= h($c['nombre']) ?></td>
                            <td><?= h($c['departamento']) ?></td>
                            <td><?= h($c['pais']) ?></td>
                            <td><?= h($c['codigo_postal']) ?></td>
                            <td class="num"><?= $c['clientes'] ?></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="ciudades.php?accion=editar&id=<?= $c['id_ciudad'] ?>">Editar</a>
                                <?= boton_post(array('accion' => 'eliminar', 'id_ciudad' => $c['id_ciudad']), '¿Eliminar la ciudad ' . $c['nombre'] . '?') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr><td colspan="7" class="vacio">No hay ciudades que coincidan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
