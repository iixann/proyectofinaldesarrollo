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
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$productos): ?>
                <p class="text-secondary text-center my-4">No hay productos activos.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col">Stock actual</th><th scope="col">Stock mínimo</th><th scope="col">Stock deseable</th><th scope="col">Estado de stock</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <?php $stockBajo = (int) $producto->stock_actual <= (int) $producto->stock_minimo; ?>
                            <tr>
                                <td><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($producto->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $producto->stock_actual, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $producto->stock_minimo, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $producto->stock_deseable, ENT_QUOTES, 'UTF-8') ?></td>
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
