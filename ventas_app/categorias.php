<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/categorias.php
 * DESCRIPCIÓN:
 *   Módulo de categorías del catálogo (`categoria`):
 *     - Permite listar, crear, editar y eliminar categorías comerciales.
 *     - Reglas de integridad en PostgreSQL: nombre único (`uq_categoria_nombre`)
 *       y restricción `ON DELETE RESTRICT` (`fk_articulo_categoria`) que impide
 *       eliminar categorías con artículos asociados.
 * ============================================================================
 */

require_once 'includes/funciones.php';

$accion  = get('accion');
$errores = array();
$datos   = array(
    'id_categoria' => '',
    'nombre'       => '',
    'descripcion'  => '',
);

/* ============================================================================
 * 1. PROCESAMIENTO DE FORMULARIOS (POST: ELIMINAR, CREAR O EDITAR CATEGORÍA)
 * ============================================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1.1 Eliminar categoría
    if (post('accion') === 'eliminar') {
        try {
            consulta('DELETE FROM categoria WHERE id_categoria = ?', array((int)post('id_categoria')));
            flash('ok', 'Categoría eliminada.');
        } catch (PDOException $e) {
            flash('error', mensaje_error_bd($e));
        }
        redirigir('categorias.php');
    }

    // 1.2 Crear o actualizar categoría
    $datos = array(
        'id_categoria' => post('id_categoria'),
        'nombre'       => post('nombre'),
        'descripcion'  => post('descripcion'),
    );

    validar_texto($datos['nombre'], 'Nombre', 80, $errores);
    validar_texto($datos['descripcion'], 'Descripción', 255, $errores, false);

    if (!$errores) {
        $descripcion = $datos['descripcion'] === '' ? null : $datos['descripcion'];

        try {
            if ($datos['id_categoria'] === '') {
                consulta(
                    'INSERT INTO categoria (nombre, descripcion) VALUES (?, ?)',
                    array($datos['nombre'], $descripcion)
                );
                flash('ok', 'Categoría registrada.');
            } else {
                consulta(
                    'UPDATE categoria SET nombre = ?, descripcion = ? WHERE id_categoria = ?',
                    array($datos['nombre'], $descripcion, (int)$datos['id_categoria'])
                );
                flash('ok', 'Categoría actualizada.');
            }
            redirigir('categorias.php');
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }

    $accion = 'formulario';
}

/* ============================================================================
 * 2. PREPARACIÓN Y RENDERIZADO DE VISTAS
 * ============================================================================ */

if ($accion === 'editar') {
    $datos = fila('SELECT * FROM categoria WHERE id_categoria = ?', array((int)get('id')));
    if (!$datos) {
        flash('error', 'La categoría no existe.');
        redirigir('categorias.php');
    }
    $accion = 'formulario';
}

if ($accion === 'nueva') {
    $accion = 'formulario';
}

$titulo = 'Categorías';
$activo = 'categorias.php';
include 'includes/encabezado.php';

if ($accion === 'formulario'): ?>
    <div class="encabezado">
        <h1><?= $datos['id_categoria'] === '' ? 'Nueva categoría' : 'Editar categoría' ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <form method="post" class="tarjeta formulario">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id_categoria" value="<?= h($datos['id_categoria']) ?>">

        <div class="completo">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="80" required value="<?= h($datos['nombre']) ?>">
        </div>

        <div class="completo">
            <label for="descripcion">Descripción (opcional)</label>
            <textarea id="descripcion" name="descripcion" maxlength="255" rows="3"><?= h($datos['descripcion']) ?></textarea>
        </div>

        <div class="completo botones">
            <button class="boton">Guardar</button>
            <a class="boton secundario" href="categorias.php">Cancelar</a>
        </div>
    </form>

<?php else:
    $lista = filas(
        'SELECT
             ca.*,
             (SELECT COUNT(*) FROM articulo a WHERE a.id_categoria = ca.id_categoria) AS articulos
         FROM categoria ca
         ORDER BY ca.nombre'
    );
?>
    <div class="encabezado">
        <div>
            <h1>Categorías</h1>
            <p class="subtitulo">Líneas en las que se agrupa el catálogo.</p>
        </div>
        <a class="boton" href="categorias.php?accion=nueva">+ Nueva categoría</a>
    </div>

    <div class="tarjeta tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th class="num">Artículos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $c): ?>
                    <tr>
                        <td><?= $c['id_categoria'] ?></td>
                        <td><?= h($c['nombre']) ?></td>
                        <td><?= h($c['descripcion']) ?></td>
                        <td class="num">
                            <a href="articulos.php?categoria=<?= $c['id_categoria'] ?>"><?= $c['articulos'] ?></a>
                        </td>
                        <td class="acciones">
                            <a class="boton secundario chico" href="categorias.php?accion=editar&id=<?= $c['id_categoria'] ?>">Editar</a>
                            <form method="post" onsubmit="return confirm(<?= js('¿Eliminar la categoría ' . $c['nombre'] . '?') ?>)">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_categoria" value="<?= $c['id_categoria'] ?>">
                                <button class="boton peligro chico">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$lista): ?>
                    <tr>
                        <td colspan="5" class="vacio">No hay categorías.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif;

include 'includes/pie.php';
