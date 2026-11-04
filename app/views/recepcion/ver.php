<!DOCTYPE html>
<html lang="es">
<head><title>Detalle de recepción | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'recepciones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a href="/proyectofinaldesarrollo/recepcion/index">Volver a Recepciones</a>
        <h1 class="h2 fw-bold my-4">Recepción #<?= (int) $recepcion->id_recepcion ?></h1>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <p><strong>Estado:</strong> <span class="badge <?= $recepcion->estado === 'ACEPTADA' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= htmlspecialchars($recepcion->estado, ENT_QUOTES, 'UTF-8') ?></span></p>
            <p><strong>Fecha:</strong> <?= htmlspecialchars($recepcion->fecha_recepcion, ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Proveedor:</strong> <?= htmlspecialchars($recepcion->razon_social, ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Usuario receptor:</strong> <?= htmlspecialchars($recepcion->nombre . ' ' . $recepcion->apellido, ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Origen:</strong> <?php if ($recepcion->id_compra !== null): ?><a href="/proyectofinaldesarrollo/compra/ver/<?= (int) $recepcion->id_compra ?>">Compra #<?= (int) $recepcion->id_compra ?></a><?php else: ?>Directa<?php endif; ?></p>
            <p><strong>Observación:</strong> <?= htmlspecialchars($recepcion->observacion ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($recepcion->estado === 'RECHAZADA'): ?><p><strong>Motivo de rechazo:</strong> <?= htmlspecialchars($recepcion->motivo_rechazo ?? '', ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <div class="table-responsive"><table class="table"><thead><tr><th>Producto</th><th>Código EAN</th><th>Cantidad esperada</th><th>Cantidad recibida</th></tr></thead><tbody>
                <?php foreach ($detalles as $detalle): ?><tr><td><?= htmlspecialchars($detalle->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($detalle->codigo_barras, ENT_QUOTES, 'UTF-8') ?></td><td><?= $detalle->cantidad_esperada === null ? 'No corresponde' : (int) $detalle->cantidad_esperada ?></td><td><?= (int) $detalle->cantidad_recibida ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="text-secondary small">Registro histórico de solo lectura. <?= $recepcion->estado === 'ACEPTADA' ? 'Esta recepción incorporó stock.' : 'Esta recepción no modificó stock.' ?></p>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
