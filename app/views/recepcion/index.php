<!DOCTYPE html>
<html lang="es">
<head><title>Recepción de mercadería | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'recepciones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><h1 class="h2 fw-bold">Recepción de mercadería</h1><a class="btn btn-primary" href="/proyectofinaldesarrollo/recepcion/directa">Nueva recepción directa</a></div>
        <p class="text-secondary">Solo una recepción ACEPTADA incorpora mercadería al stock. No se permiten recepciones parciales.</p>
        <h2 class="h4">Compras pendientes de recepción</h2>
        <section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body">
            <?php if (!$compras): ?><p class="text-secondary">No hay compras pendientes.</p><?php else: ?>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Compra</th><th>Proveedor</th><th>Fecha</th><th>Comprobante</th><th>Usuario</th><th>Total</th><th>Acción</th></tr></thead><tbody>
                <?php foreach ($compras as $compra): ?><tr>
                    <td>#<?= (int) $compra->id_compra ?></td><td><?= htmlspecialchars($compra->razon_social, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($compra->fecha_compra, ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($compra->numero_comprobante ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($compra->nombre . ' ' . $compra->apellido, ENT_QUOTES, 'UTF-8') ?></td><td>$ <?= htmlspecialchars((string) $compra->total, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/recepcion/compra/<?= (int) $compra->id_compra ?>">Recepcionar</a></td>
                </tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div></section>
        <h2 class="h4">Histórico de recepciones</h2>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body">
            <?php if (!$recepciones): ?><p class="text-secondary">No hay recepciones registradas.</p><?php else: ?>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Fecha</th><th>Proveedor</th><th>Origen</th><th>Usuario</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
                <?php foreach ($recepciones as $recepcion): ?><tr>
                    <td><?= (int) $recepcion->id_recepcion ?></td><td><?= htmlspecialchars($recepcion->fecha_recepcion, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($recepcion->razon_social, ENT_QUOTES, 'UTF-8') ?></td><td><?= $recepcion->id_compra === null ? 'Directa' : 'Compra #' . (int) $recepcion->id_compra ?></td>
                    <td><?= htmlspecialchars($recepcion->nombre . ' ' . $recepcion->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge <?= $recepcion->estado === 'ACEPTADA' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= htmlspecialchars($recepcion->estado, ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/recepcion/ver/<?= (int) $recepcion->id_recepcion ?>">Ver</a></td>
                </tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
