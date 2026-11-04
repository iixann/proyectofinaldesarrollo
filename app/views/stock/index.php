<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de stock | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'stock'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h1 class="h2 fw-bold mb-0">Gestión de stock</h1>
            <a class="btn btn-outline-primary" href="/proyectofinaldesarrollo/stock/movimientos">Ver movimientos</a>
        </div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="get" action="/proyectofinaldesarrollo/stock/index" class="row g-3 align-items-end mb-4">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="nombre">Nombre</label>
                <input class="form-control" type="text" id="nombre" name="nombre" maxlength="150" value="<?= htmlspecialchars($filtros['nombre'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="codigo_barras">Código EAN</label>
                <input class="form-control" type="text" id="codigo_barras" name="codigo_barras" maxlength="50" placeholder="Código exacto" value="<?= htmlspecialchars($filtros['codigo_barras'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="id_categoria">Categoría</label>
                <select class="form-select" id="id_categoria" name="id_categoria">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= (int) $categoria->id_categoria ?>" <?= (string) $categoria->id_categoria === $filtros['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label" for="id_proveedor">Proveedor</label>
                <select class="form-select" id="id_proveedor" name="id_proveedor">
                    <option value="">Todos los proveedores</option>
                    <?php foreach ($proveedores as $proveedor): ?>
                        <option value="<?= (int) $proveedor->id_proveedor ?>" <?= (string) $proveedor->id_proveedor === $filtros['id_proveedor'] ? 'selected' : '' ?>><?= htmlspecialchars($proveedor->razon_social, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-outline-primary" type="submit">Buscar</button>
                <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/stock/index">Limpiar</a>
            </div>
        </form>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$productos): ?>
                <p class="text-secondary text-center my-4">No hay productos activos para los filtros seleccionados.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col">Stock actual</th><th scope="col">Stock mínimo</th><th scope="col">Estado de stock</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <?php $stockBajo = (int) $producto->stock_actual <= (int) $producto->stock_minimo; ?>
                            <tr>
                                <td><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($producto->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $producto->stock_actual, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $producto->stock_minimo, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $stockBajo ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis' ?>"><?= $stockBajo ? 'STOCK BAJO' : 'NORMAL' ?></span></td>
                                <td><div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/stock/ajustar/<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>">Ajustar stock</a>
                                    <a class="btn btn-outline-secondary btn-sm" href="/proyectofinaldesarrollo/stock/movimientos/<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>">Ver movimientos</a>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
