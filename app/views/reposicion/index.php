<!DOCTYPE html>
<html lang="es">
<head>
    <title>Reposición | Identidad Singular</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'reposicion'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-3">Reposición</h1>
        <p class="text-secondary">Productos activos con stock actual menor o igual al mínimo. Esta consulta no modifica existencias ni genera compras.</p>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$productos): ?><p class="text-secondary text-center my-4">No hay productos que necesiten reposición.</p><?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Producto</th><th>Código EAN</th><th>Categoría</th><th>Proveedores</th><th>Stock actual</th><th>Stock mínimo</th><th>Estado de stock</th></tr></thead>
                    <tbody><?php foreach ($productos as $producto): ?><tr>
                        <td><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($producto->codigo_barras, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($producto->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($producto->proveedores ?? 'Sin proveedores asociados', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $producto->stock_actual ?></td><td><?= (int) $producto->stock_minimo ?></td>
                        <td><span class="badge bg-warning-subtle text-warning-emphasis">STOCK BAJO</span></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
