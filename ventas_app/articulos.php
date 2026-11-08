<?php
/**
 * Catálogo de artículos (tabla articulo): listar, filtrar, crear, editar y eliminar.
 *   - La base exige precio > 0 y stock >= 0 (CHECK) y no deja borrar un artículo
 *     que ya se vendió (fk_detalle_articulo ON DELETE RESTRICT).
 *   - Imagen opcional en la tabla articulo_imagen (JPG, PNG o WEBP de máximo 2 MB).
 *     Se borra sola si se borra el artículo (ON DELETE CASCADE).
 */

require_once 'includes/funciones.php';
require_admin();

$accion  = get('accion');
$errores = array();
$datos   = array('id_articulo' => '', 'nombre' => '', 'descripcion' => '', 'precio' => '', 'stock' => '0', 'id_categoria' => '');

// ---------- Formularios enviados ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf('articulos.php');

    if (post('accion') === 'eliminar') {
        eliminar_y_volver('DELETE FROM articulo WHERE id_articulo = ?', (int)post('id_articulo'), 'Artículo eliminado.', 'articulos.php');
    }

    // Guardar: sin id es nuevo, con id es edición
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
    $imagen = leer_imagen_subida('imagen', $errores);

    if (!$errores) {
        $params = array($datos['nombre'], nulo_si_vacio($datos['descripcion']), $datos['precio'],
                        (int)$datos['stock'], (int)$datos['id_categoria']);
        try {
            // Artículo e imagen en una sola transacción: si falla la imagen, el artículo tampoco se guarda.
            $mensaje = en_transaccion(function () use ($datos, $params, $imagen) {
                if ($datos['id_articulo'] === '') {
                    $id = (int)valor('INSERT INTO articulo (nombre, descripcion, precio, stock, id_categoria)
                                      VALUES (?, ?, ?, ?, ?) RETURNING id_articulo', $params);
                    $mensaje = 'Artículo registrado.';
                } else {
                    $id = (int)$datos['id_articulo'];
                    $params[] = $id;
                    consulta('UPDATE articulo SET nombre = ?, descripcion = ?, precio = ?, stock = ?, id_categoria = ?
                              WHERE id_articulo = ?', $params);
                    $mensaje = 'Artículo actualizado.';
                }

                if ($imagen) {
                    guardar_imagen_articulo($id, $imagen);
                } elseif (post('quitar_imagen') === '1') {
                    consulta('DELETE FROM articulo_imagen WHERE id_articulo = ?', array($id));
                }
                return $mensaje;
            });
            flash('ok', $mensaje);
            redirigir('articulos.php');
        } catch (PDOException $e) {
            $errores[] = mensaje_error_bd($e);
        }
    }
    $accion = 'formulario';
}

if ($accion === 'editar') {
    $datos  = buscar_o_volver('SELECT * FROM articulo WHERE id_articulo = ?', (int)get('id'), 'El artículo no existe.', 'articulos.php');
    $accion = 'formulario';
}
if ($accion === 'nuevo') {
    $accion = 'formulario';
}

$titulo = 'Artículos';
$activo = 'articulos.php';
include 'includes/encabezado.php';

// ---------- Formulario de nuevo / editar ----------
if ($accion === 'formulario'):
    $categorias = lista_categorias();
    // Fecha de la imagen actual (null si no tiene): sirve para mostrarla
    $version_imagen = $datos['id_articulo'] === '' ? null
        : (valor('SELECT actualizada FROM articulo_imagen WHERE id_articulo = ?', array((int)$datos['id_articulo'])) ?: null);
?>
    <div class="encabezado">
        <h1><?= $datos['id_articulo'] === '' ? 'Nuevo artículo' : 'Editar artículo' ?></h1>
    </div>

    <?php mostrar_errores($errores); ?>

    <?php if (!$categorias): ?>
        <div class="alerta alerta-info">Primero registre al menos una <a href="categorias.php?accion=nueva">categoría</a>.</div>
    <?php endif; ?>

    <form method="post" class="tarjeta formulario" enctype="multipart/form-data">
        <?= csrf_campo() ?>
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

        <div class="completo">
            <label for="imagen">Imagen (opcional)</label>
            <div class="campo-imagen">
                <?= imagen_articulo($datos['id_articulo'], $version_imagen, $datos['nombre'], 'imagen-articulo imagen-formulario') ?>
                <div>
                    <input id="imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp">
                    <div class="ayuda">JPG, PNG o WEBP de máximo 2 MB. <?= $version_imagen ? 'Si eliges otra, reemplaza la actual.' : '' ?></div>
                    <?php if ($version_imagen): ?>
                        <label class="casilla"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la imagen actual</label>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="completo botones">
            <button class="boton">Guardar</button>
            <a class="boton secundario" href="articulos.php">Cancelar</a>
        </div>
    </form>

<?php else:
    // ---------- Lista con búsqueda y filtro por categoría ----------
    $categorias = lista_categorias();
    $q   = get('q');
    $cat = get('categoria');

    $sql = 'SELECT a.*, c.nombre AS categoria, ai.actualizada AS imagen_version,
                   (SELECT COALESCE(SUM(cantidad), 0) FROM detalle_venta d WHERE d.id_articulo = a.id_articulo) AS vendidos
            FROM articulo a
            JOIN categoria c ON c.id_categoria = a.id_categoria
            LEFT JOIN articulo_imagen ai ON ai.id_articulo = a.id_articulo
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
                                <div class="celda-articulo">
                                    <?= imagen_articulo($a['id_articulo'], $a['imagen_version'], $a['nombre'], 'imagen-articulo imagen-mini') ?>
                                    <div><?= h($a['nombre']) ?><div class="ayuda"><?= h($a['descripcion']) ?></div></div>
                                </div>
                            </td>
                            <td><?= h($a['categoria']) ?></td>
                            <td class="num"><?= dinero($a['precio']) ?></td>
                            <td class="num <?= $a['stock'] <= STOCK_BAJO ? 'stock-bajo' : '' ?>"><?= $a['stock'] ?></td>
                            <td class="num"><?= $a['vendidos'] ?></td>
                            <td class="acciones">
                                <a class="boton secundario chico" href="articulos.php?accion=editar&id=<?= $a['id_articulo'] ?>">Editar</a>
                                <?= boton_post(array('accion' => 'eliminar', 'id_articulo' => $a['id_articulo']), '¿Eliminar el artículo ' . $a['nombre'] . '?') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$lista): ?>
                        <tr><td colspan="7" class="vacio">No hay artículos que coincidan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif;

include 'includes/pie.php';
