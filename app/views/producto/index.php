<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de productos | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'productos'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <!-- Muestra los datos de productos enviados por el controlador. -->
    <main class="container-fluid pantalla-tabla-ancha py-4 py-md-5 flex-grow-1" id="contenido">
        <?php if (\app\controllers\SessionController::esAdministrador()): ?>
            <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/admin/index">Volver al panel principal</a>
        <?php endif; ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
            <h1 class="h2 fw-bold mb-0">Gestión de productos</h1>
            <?php if ($puedeGestionar): ?><a class="btn btn-primary" href="/proyectofinaldesarrollo/producto/crear">Nuevo producto</a><?php endif; ?>
        </div>
        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <!-- Envía los campos por GET a producto/index. -->
        <form method="get" action="/proyectofinaldesarrollo/producto/index" class="filtros-listado row g-3 align-items-end mb-4">
            <!-- La consulta EAN muestra catálogo y existencias sin modificar stock. -->
            <input type="hidden" name="escaneado" value="0">
            <div class="col-sm-6 col-lg-3"><label class="form-label" for="codigo_barras">Código EAN</label>
                <input class="form-control" id="codigo_barras" name="codigo_barras" maxlength="50" value="<?= htmlspecialchars($filtros['codigo_barras'], ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn btn-outline-primary mt-2" type="button" data-escanear-ean data-ean-modo="filtro" data-ean-objetivo="#codigo_barras">📷 Escanear EAN</button>
            </div>
            <div class="col-sm-4"><label class="form-label" for="nombre">Nombre del producto</label><input class="form-control" type="text" id="nombre" name="nombre" maxlength="150" value="<?= htmlspecialchars((string) $filtros['nombre'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-sm-3"><label class="form-label" for="id_categoria">Categoría</label><select class="form-select" id="id_categoria" name="id_categoria"><option value="">Todas las categorías</option>
                <?php foreach ($categorias as $categoria): ?><option value="<?= (int) $categoria->id_categoria ?>" <?= (string) $categoria->id_categoria === $filtros['id_categoria'] ? 'selected' : '' ?>><?= htmlspecialchars($categoria->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select></div>
            <div class="col-sm-3"><label class="form-label" for="id_proveedor">Proveedor</label><select class="form-select" id="id_proveedor" name="id_proveedor"><option value="">Todos los proveedores</option>
                <?php foreach ($proveedores as $proveedor): ?><option value="<?= (int) $proveedor->id_proveedor ?>" <?= (string) $proveedor->id_proveedor === $filtros['id_proveedor'] ? 'selected' : '' ?>><?= htmlspecialchars($proveedor->razon_social, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select></div>

            <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Buscar</button> <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/producto/index">Limpiar</a></div>
        </form>
        <?php // Resume el producto exacto enviado por ProductoModel para la consulta móvil. ?>
        <?php if ($filtros['codigo_barras'] !== '' && count($productos) === 1): ?>
        <?php $consultado = $productos[0]; ?>
        <section class="card border-primary mb-3" data-ean-resumen aria-label="Producto consultado"><div class="card-body">
            <h2 class="h5"><?= htmlspecialchars($consultado->nombre_producto, ENT_QUOTES, 'UTF-8') ?></h2>
            <dl class="row mb-0">
                <dt class="col-6">EAN</dt><dd class="col-6"><?= htmlspecialchars((string) $consultado->codigo_barras, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-6">Stock actual</dt><dd class="col-6"><?= (int) $consultado->stock_actual ?></dd>
                <dt class="col-6">Stock mínimo</dt><dd class="col-6"><?= (int) $consultado->stock_minimo ?></dd>
                <dt class="col-6">Categoría</dt><dd class="col-6"><?= htmlspecialchars($consultado->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-6">Precio</dt><dd class="col-6">$ <?= htmlspecialchars(number_format((float) $consultado->precio_venta, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-6">Proveedores</dt><dd class="col-6"><?= htmlspecialchars($consultado->proveedores ?? 'Sin proveedores asociados', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-6">Estado</dt><dd class="col-6"><?= (int) $consultado->estado === 1 ? 'Activo' : 'Inactivo' ?> · <?= (int) $consultado->stock_actual <= (int) $consultado->stock_minimo ? 'Stock bajo' : 'Stock normal' ?></dd>
            </dl>
        </div></section>
        <?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4" aria-label="Listado de productos">
            <div class="card-body p-3 p-md-4">
                <?php if (!$productos): ?>
                    <p class="text-secondary text-center my-4">No se encontraron productos. Probá otros filtros.</p>
                <?php else: ?>
                    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p>
                        <!-- Listado generado con los registros recibidos del controlador. -->
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Producto</th><th scope="col">Código EAN</th><th scope="col">Categoría</th><th scope="col">Proveedores</th><th scope="col">Precio</th><th scope="col">Stock actual</th><th scope="col">Stock mínimo</th><th scope="col">Estado de stock</th><th scope="col">Estado</th><?php if ($puedeGestionar): ?><th scope="col" class="col-acciones-producto">Acciones</th><?php endif; ?></tr></thead>
                            <tbody>
                                <?php foreach ($productos as $producto): ?>
                                    <tr data-ean-localizado="<?= $filtros['codigo_barras'] !== '' && (string) $producto->codigo_barras === $filtros['codigo_barras'] ? '1' : '0' ?>">
                                        <td><?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="col-producto"><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="col-ean"><?= htmlspecialchars($producto->codigo_barras ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($producto->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($producto->proveedores ?? 'Sin proveedores asociados', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-nowrap">$ <?= htmlspecialchars(number_format((float) $producto->precio_venta, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars((string) $producto->stock_actual, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars((string) $producto->stock_minimo, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge <?= (int) $producto->stock_actual <= (int) $producto->stock_minimo ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success-emphasis' ?>"><?= (int) $producto->stock_actual <= (int) $producto->stock_minimo ? 'STOCK BAJO' : 'NORMAL' ?></span></td>
                                        <td><span class="badge rounded-pill text-nowrap <?= (int) $producto->estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int) $producto->estado === 1 ? 'ACTIVO' : 'INACTIVO' ?></span></td>
                                        <?php // Agrupa únicamente las acciones ya autorizadas para mantener el catálogo. ?>
                                        <?php if ($puedeGestionar): ?><td class="col-acciones-producto"><div class="dropdown">
                                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-producto-acciones aria-expanded="false" aria-label="Acciones de <?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?>">Acciones</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="/proyectofinaldesarrollo/producto/editar/<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>">Editar</a></li>
                                                <li><a class="dropdown-item" href="/proyectofinaldesarrollo/producto/historial/<?= (int) $producto->id_producto ?>">Auditoría</a></li>
                                                <li><a class="dropdown-item" href="/proyectofinaldesarrollo/stock/movimientos/<?= (int) $producto->id_producto ?>">Movimientos</a></li>
                                                <?php if ((int) $producto->estado === 1): ?>
                                                    <li><a class="dropdown-item" href="/proyectofinaldesarrollo/stock/ajustar/<?= (int) $producto->id_producto ?>">Ajustar stock</a></li>
                                                    <li><a class="dropdown-item" href="/proyectofinaldesarrollo/stock/decomiso/<?= (int) $producto->id_producto ?>">Registrar decomiso</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><form data-confirmar-baja="¿Desea dar de baja al producto <?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?>?" method="post" action="/proyectofinaldesarrollo/producto/baja/<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button class="dropdown-item text-danger" type="submit">Dar de baja</button>
                                                    </form></li>
                                                <?php else: ?>
                                                    <li><form method="post" action="/proyectofinaldesarrollo/producto/reactivar/<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button class="dropdown-item" type="submit">Reactivar</button>
                                                    </form></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div></td><?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <?php require APP_PATH . 'views/inc/ean_scanner.php'; ?>
    <?php if ($puedeGestionar): ?>
    <!-- Mantiene el menú completo fuera del recorte de la tabla responsive. -->
    <script src="/proyectofinaldesarrollo/js/producto-acciones.js?v=<?= substr(hash_file('sha256', 'public/js/producto-acciones.js'), 0, 12) ?>"></script>
    <?php endif; ?>
</body>
</html>
