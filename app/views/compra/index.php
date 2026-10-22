<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de compras | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'compras'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h1 class="h2 fw-bold mb-0">Gestión de compras</h1>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/compra/crear">Nueva compra</a>
        </div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$compras): ?>
                <p class="text-secondary text-center my-4">No hay compras registradas.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Fecha</th><th scope="col">Proveedor</th><th scope="col">Comprobante</th><th scope="col">Usuario</th><th scope="col">Total</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $compra->id_compra, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($compra->fecha_compra, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($compra->razon_social, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($compra->numero_comprobante ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($compra->nombre . ' ' . $compra->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>$ <?= htmlspecialchars((string) $compra->total, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/compra/ver/<?= htmlspecialchars((string) $compra->id_compra, ENT_QUOTES, 'UTF-8') ?>">Ver</a></td>
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
