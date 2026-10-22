<!DOCTYPE html>
<html lang="es">
<head>
    <title>Movimientos de stock | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'stock'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/stock/index">Volver a Stock</a>
        <h1 class="h2 fw-bold my-4">Movimientos de stock</h1>
        <?php if ($producto): ?><p class="text-secondary">Producto: <?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></p><a class="btn btn-outline-secondary mb-3" href="/proyectofinaldesarrollo/stock/movimientos">Ver todos los movimientos</a><?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$movimientos): ?>
                <p class="text-secondary text-center my-4">No hay movimientos registrados.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">Fecha</th><th scope="col">Producto</th><th scope="col">Tipo</th><th scope="col">Cantidad</th><th scope="col">Stock anterior</th><th scope="col">Stock nuevo</th><th scope="col">Usuario</th><th scope="col">Observación</th></tr></thead>
                    <tbody>
                        <?php foreach ($movimientos as $movimiento): ?>
                            <tr>
                                <td><?= htmlspecialchars($movimiento->fecha_movimiento, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $movimiento->tipo_movimiento === 'AJUSTE_ENTRADA' ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis' ?>"><?= htmlspecialchars($movimiento->tipo_movimiento, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string) $movimiento->cantidad, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $movimiento->stock_anterior, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $movimiento->stock_nuevo, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento->nombre . ' ' . $movimiento->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($movimiento->observacion ?? '', ENT_QUOTES, 'UTF-8') ?></td>
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
