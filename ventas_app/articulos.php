<?php
/**
 * ============================================================================
 * ARCHIVO: ventas_app/articulos.php
 * DESCRIPCIÓN:
 *   Módulo de catálogo de artículos (`articulo`):
 *     - Listado con filtro por texto y categoría, mostrando existencias actuales
 *       y total de unidades vendidas.
 *     - Creación, edición y eliminación de artículos.
 *     - Restricciones en PostgreSQL: `precio > 0` (`ck_articulo_precio`),
 *       `stock >= 0` (`ck_articulo_stock`) y protección contra borrado de
 *       artículos ya facturados (`fk_detalle_articulo ON DELETE RESTRICT`).
 * ============================================================================
 */

require_once 'includes/funciones.php';

$accion  = get('accion');
$errores = array();
$datos   = array(
    'id_articulo'  => '',
    'nombre'       => '',
    'descripcion'  => '',
    'precio'       => '',
    'stock'        => '0',
    'id_categoria' => '',
);

/* ============================================================================
 * 1. PROCESAMIENTO DE FORMULARIOS (POST: ELIMINAR, CREAR O EDITAR ARTÍCULO)
 * ============================================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1.1 Eliminar artículo
    if (post('accion') === 'eliminar') {
        try {
            consulta('DELETE FROM articulo WHERE id_articulo = ?', array((int)post('id_articulo')));
            flash('ok', 'Artículo eliminado.');
        } catch (PDOException $e) {
            flash('error', mensaje_error_bd($e));
        }
        redirigir('articulos.php');
    }

    // 1.2 Crear o actualizar artículo
    $datos = array(
        'id_articulo'  => post('id_articulo'),
        'nombre'       => post('nombre'),
        'descripcion'  => post('descripcion'),
        'precio'       => str_replace(',', '.', post('precio')),
        'stock'        => post('stock'),
        'id_categoria' => post('id_categoria'),
    );

    validar_texto($datos['nombre'], 'Nombre', 120, $errores);
    validar_texto($datos['descripcion'], 'Descripción', 255, $errores, false);
    validar_dinero($datos['precio'], 'Precio', $errores);
    validar_entero($datos['stock'], 'Stock', $errores, 0);

    if (!fila('SELECT 1 FROM categoria WHERE id_categoria = ?', array((int)$datos['id_categoria']))) {
        $errores[] = 'Seleccione una categoría.';
    }

    if (!$errores) {
        $descripcion = $datos['descripcion'] === '' ? null : $datos['descripcion'];
        $params = array(
            $datos['nombre'],
            $descripcion,
            $datos['precio'],
            (int)$datos['stock'],
            (int)$datos['id_categoria'],
        );

        try {
            if ($datos['id_articulo'] === '') {
                consulta(
                    'INSERT INTO articulo (nombre, descripcion, precio, stock, id_categoria)
                     VALUES (?, ?, ?, ?, ?)',
                    $params
                );
                flash('ok', 'Artículo registrado.');
            } else {
                $params[] = (int)$datos['id_articulo'];
                consulta(
                    'UPDATE articulo
                     SET nombre = ?, descripcion = ?, precio = ?, stock = ?, id_categoria = ?
                     WHERE id_articulo = ?',
                    $params
                );
                flash('ok', 'Artículo actualizado.');
            }
            redirigir('articulos.php');
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
    $datos = fila('SELECT * FROM articulo WHERE id_articulo = ?', array((int)get('id')));
    if (!$datos) {
        flash('error', 'El artículo no existe.');
        redirigir('articulos.php');
    }
    $accion = 'formulario';
}

if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$categorias = array();
foreach (filas('SELECT id_categoria, nombre FROM categoria ORDER BY nombre') as $c) {
    $categorias[$c['id_categoria']] = $c['nombre'];
}

$titulo = 'Artículos';
$activo = 'articulos.php';
include 'includes/encabezado.php';

if ($accion === 'formulario'): ?>
    <div class="encabezado">
        <h1><?= $datos['id_articulo'] === '' ? 'Nuevo artículo' : 'Editar artículo' ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if (!$categorias): ?>
        <div class="alerta alerta-info">
            Primero registre al menos una <a href="categorias.php?accion=nueva">categoría</a>.
        </div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id_articulo" value="<?= h($datos['id_articulo']) ?>">

        <div class="completo">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="120" required value="<?= h($datos['nombre']) ?>">
        </div>

        <div class="completo">
            <label for="descripcion">Descripción (opcional)</label>
            <textarea id="descripcion" name="descripcion" maxlength="255" rows="2"><?= h($datos['descripcion']) ?></textarea>
        </div>

        <div>
            <label for="precio">Precio unitario (COP)</label>
            <input id="precio" name="precio" type="number" min="0.01" step="0.01" required value="<?= h($datos['precio']) ?>">
            <div class="ayuda">Mayor que 0.</div>
        </div>

        <div>
            <label for="stock">Stock disponible</label>
            <input id="stock" name="stock" type="number" min="0" step="1" required value="<?= h($datos['stock']) ?>">
            <div class="ayuda">0 o más. Baja solo al vender.</div>
        </div>

        <div class="completo">
            <label for="id_categoria">Categoría</label>
            <select id="id_categoria" name="id_categoria" required>
                <option value="">Seleccione…</option>
                <?= opciones($categorias, $datos['id_categoria']) ?>
            </select>
        </div>

        <div class="completo botones">
            <button class="boton">Guardar</button>
            <a class="boton secundario" href="articulos.php">Cancelar</a>
        </div>
    </form>

<?php else:
    $q   = get('q');
    $cat = get('categoria');

    $sql = 'SELECT
                a.*,
                c.nombre AS categoria,
                (SELECT COALESCE(SUM(cantidad), 0)
                 FROM detalle_venta d
                 WHERE d.id_articulo = a.id_articulo) AS vendidos
            FROM articulo a
            JOIN categoria c ON c.id_categoria = a.id_categoria
            WHERE (a.nombre ILIKE ? OR a.descripcion ILIKE ?)';

    $params = array("%$q%", "%$q%");

    if ($cat !== '') {
        $sql .= ' AND a.id_categoria = ?';
        $params[] = (int)$cat;
    }

    $lista = filas($sql . ' ORDER BY a.nombre', $params);
?>
    <div class="encabezado">
        <div>
            <h1>Artículos</h1>
            <p class="subtitulo">Catálogo con precio e inventario actual.</p>
        </div>
        <a class="boton" href="articulos.php?accion=nuevo">+ Nuevo artículo</a>
    </div>

    <div class="tarjeta">
        <form class="filtros">
            <input name="q" placeholder="Buscar artículo" value="<?= h($q) ?>">
            <select name="categoria">
                <option value="">Todas las categorías</option>
                <?= opciones($categorias, $cat) ?>
            </select>
            <button class="boton secundario">Filtrar</button>
        </form>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Artículo</th>
                        <th>Categoría</th>
                        <th class="num">Precio</th>
                        <th class="num">Stock</th>
                        <th class="num">Vendidos</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $a): ?>
                        <tr>
                            <td><?= $a['id_articulo'] ?></td>
                            <td>
                                <?= h($a['nombre']) ?>
                                <div class="ayuda"><?= h($a['descripcion']) ?></div>
                            </td>
                            <td><?= h($a['categoria']) ?></td>
                            <td class="num"><?= dinero($a['precio']) ?></td>
                            <td class="num <?= $a['stock'] <= STOCK_BAJO ? 'stock-bajo' : '' ?>">
                                <?= $a['stock'] ?>
                            </td>
                            <td class="num"><?= $a['vendidos'] ?></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="articulos.php?accion=editar&id=<?= $a['id_articulo'] ?>">Editar</a>
                                <form method="post" onsubmit="return confirm(<?= js('¿Eliminar el artículo ' . $a['nombre'] . '?') ?>)">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_articulo" value="<?= $a['id_articulo'] ?>">
                                    <button class="boton peligro chico">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr>
                            <td colspan="7" class="vacio">No hay artículos que coincidan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
