<!DOCTYPE html>
<html lang="es">
<head>
    <title>Ver compra | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'compras'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/compra/index">Volver a Compras</a>
        <h1 class="h2 fw-bold my-4">Compra #<?= htmlspecialchars((string) $compra->id_compra, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><strong>Estado de recepción:</strong> <span class="badge <?= $compra->estado_recepcion === 'PENDIENTE' ? 'bg-warning-subtle text-warning-emphasis' : ($compra->estado_recepcion === 'RECEPCIONADA' ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis') ?>"><?= htmlspecialchars($compra->estado_recepcion, ENT_QUOTES, 'UTF-8') ?></span></p>
        <?php if ((int) $compra->estado === 1 && $compra->estado_recepcion === 'PENDIENTE'): ?><p><a class="btn btn-success" href="/proyectofinaldesarrollo/recepcion/compra/<?= (int) $compra->id_compra ?>">Recepcionar mercadería</a></p>
        <?php elseif ($recepcion): ?><p><a href="/proyectofinaldesarrollo/recepcion/ver/<?= (int) $recepcion->id_recepcion ?>">Ver recepción</a></p>
        <?php elseif ($compra->estado_recepcion === 'RECEPCIONADA'): ?><p class="alert alert-info">Compra histórica del circuito anterior: su stock ya fue incorporado. No admite una nueva recepción ni se generó una recepción retroactiva.</p><?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
            <dl class="row mb-0">
                <dt class="col-sm-3">Fecha</dt><dd class="col-sm-9"><?= htmlspecialchars($compra->fecha_compra, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Proveedor</dt><dd class="col-sm-9"><?= htmlspecialchars($compra->razon_social, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Usuario</dt><dd class="col-sm-9"><?= htmlspecialchars($compra->nombre . ' ' . $compra->apellido, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Comprobante</dt><dd class="col-sm-9"><?= htmlspecialchars($compra->numero_comprobante ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Observación</dt><dd class="col-sm-9"><?= htmlspecialchars($compra->observacion ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-3">Total</dt><dd class="col-sm-9 fw-bold">$ <?= htmlspecialchars((string) $compra->total, ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        </div></section>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <h2 class="h5 mb-3">Detalle de la compra</h2>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Cantidad</th><th scope="col">Precio unitario</th><th scope="col">Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?= htmlspecialchars($detalle->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $detalle->cantidad, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>$ <?= htmlspecialchars((string) $detalle->precio_unitario, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>$ <?= htmlspecialchars((string) $detalle->subtotal, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
