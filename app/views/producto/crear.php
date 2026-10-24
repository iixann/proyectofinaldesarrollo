<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nuevo producto | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'productos'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Nuevo producto</h1>
        <div class="row"><div class="col-12 col-lg-8">
            <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 p-md-5">
                <?php if ($errores): ?>
                    <div class="alert alert-danger" role="alert"><ul class="mb-0">
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>
                <form method="post" action="/proyectofinaldesarrollo/producto/guardar">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="nombre_producto">Nombre del producto *</label>
                            <input class="form-control" id="nombre_producto" name="nombre_producto" value="<?= htmlspecialchars($datosProducto['nombre_producto'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="id_categoria">Categoría *</label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="">Seleccioná una categoría activa</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= htmlspecialchars((string) $categoria->id_categoria, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($datosProducto['id_categoria'] ?? '') === (string) $categoria->id_categoria ? 'selected' : '' ?>><?= htmlspecialchars($categoria->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$categorias): ?><p class="text-secondary small mt-2">No hay categorías activas. Creá una antes de guardar el producto.</p><?php endif; ?>
                            <?php if (!empty($datosProducto['id_categoria']) && !in_array((string) $datosProducto['id_categoria'], array_map('strval', array_column($categorias, 'id_categoria')), true)): ?>
                                <p class="text-warning-emphasis small mt-2">La categoría anterior ya no está activa. Seleccioná una categoría activa para guardar.</p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="codigo_barras">Código EAN *</label>
                            <input class="form-control" type="text" id="codigo_barras" name="codigo_barras" maxlength="50" value="<?= htmlspecialchars($datosProducto['codigo_barras'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($datosProducto['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="precio_venta">Precio de venta *</label>
                            <input class="form-control" type="number" min="0" step="any" id="precio_venta" name="precio_venta" value="<?= htmlspecialchars((string) ($datosProducto['precio_venta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <p class="form-label mb-2">Stock inicial: 0</p>
                            <p class="text-secondary small">Cargá el stock inicial desde Stock para registrar el movimiento.</p>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="stock_minimo">Stock mínimo</label>
                            <input class="form-control" type="number" min="0" step="1" id="stock_minimo" name="stock_minimo" value="<?= htmlspecialchars((string) ($datosProducto['stock_minimo'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="stock_deseable">Stock deseable</label>
                            <input class="form-control" type="number" min="0" step="1" id="stock_deseable" name="stock_deseable" value="<?= htmlspecialchars((string) ($datosProducto['stock_deseable'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <fieldset class="mb-4">
                        <legend class="fs-6 fw-semibold">Proveedores (opcional)</legend>
                        <?php if (!$proveedores): ?><p class="text-secondary">No hay proveedores activos disponibles.</p><?php endif; ?>
                        <div class="row g-2">
                            <?php foreach ($proveedores as $proveedor): ?>
                                <div class="col-12 col-sm-6">
                                    <label class="d-flex align-items-center gap-2 border rounded-3 p-3">
                                        <input class="form-check-input mt-0" type="checkbox" name="proveedores[]" value="<?= htmlspecialchars((string) $proveedor->id_proveedor, ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string) $proveedor->id_proveedor, $datosProducto['proveedores'] ?? [], true) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($proveedor->razon_social, ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (array_diff($datosProducto['proveedores'] ?? [], array_map('strval', array_column($proveedores, 'id_proveedor')))): ?>
                            <p class="text-warning-emphasis small mt-2">Hay proveedores asociados que ya no están activos. Al guardar se conservarán solamente los proveedores activos seleccionados.</p>
                        <?php endif; ?>
                    </fieldset>
                    <div class="d-flex flex-wrap gap-2 border-top pt-4">
                        <button class="btn btn-primary" type="submit">Guardar producto</button>
                        <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/producto/index">Cancelar</a>
                    </div>
                </form>
            </div></section>
        </div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
