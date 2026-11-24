<!DOCTYPE html>
<html lang="es">
<head><title>Mis cajas | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
<?php $seccionActual = 'sesiones'; require APP_PATH . 'views/inc/navbar.php'; ?>
<main class="container py-4 flex-grow-1" id="contenido">
    <h1 class="h2 mb-3">Sesiones de caja</h1>
    <p class="text-secondary">Elegí una caja disponible o continuá en tu sesión abierta.</p>
    <?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <!-- El controlador solo entrega disponibilidad e identificadores propios. -->
    <div class="row g-3">
    <?php foreach ($cajas as $caja): ?>
        <div class="col-md-6 col-lg-4"><section class="card h-100"><div class="card-body">
            <h2 class="h5"><?= htmlspecialchars($caja->nombre, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if ($caja->disponibilidad === 'DISPONIBLE'): ?>
                <p><span class="badge text-bg-success">Disponible</span></p>
                <a class="btn btn-primary btn-sm" href="/proyectofinaldesarrollo/sesioncaja/abrir?caja=<?= (int) $caja->id_caja ?>">Abrir sesión</a>
            <?php elseif ($caja->disponibilidad === 'PROPIA'): ?>
                <p><span class="badge text-bg-primary">Mi sesión abierta</span></p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary btn-sm" href="/proyectofinaldesarrollo/pos/index?sesion=<?= (int) $caja->mi_sesion ?>">Ir al POS</a>
                    <a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/sesioncaja/ver/<?= (int) $caja->mi_sesion ?>">Mi sesión</a>
                    <a class="btn btn-outline-secondary btn-sm" href="/proyectofinaldesarrollo/sesioncaja/cerrar/<?= (int) $caja->mi_sesion ?>">Cerrar mi sesión</a>
                </div>
            <?php else: ?>
                <p class="mb-0"><span class="badge text-bg-secondary">No disponible</span></p>
            <?php endif; ?>
        </div></section></div>
    <?php endforeach; ?>
    </div>
    <?php if (!$cajas): ?><p>No hay cajas activas.</p><?php endif; ?>
</main>
<?php require APP_PATH . 'views/inc/footer.php'; ?>
</body></html>
