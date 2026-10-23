<!DOCTYPE html>
<html lang="es">
<head>
    <title>Detalle de sesión | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'sesiones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/sesioncaja/index">Volver a sesiones</a>
        <h1 class="h2 fw-bold my-4">Sesión #<?= htmlspecialchars((string) $sesion->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?></h1>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <dl class="row mb-0">
                <dt class="col-sm-4">Caja</dt><dd class="col-sm-8"><?= htmlspecialchars($sesion->caja, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><?= htmlspecialchars($sesion->estado, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Usuario apertura</dt><dd class="col-sm-8"><?= htmlspecialchars($sesion->nombre . ' ' . $sesion->apellido, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Fecha apertura</dt><dd class="col-sm-8"><?= htmlspecialchars($sesion->fecha_apertura, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-4">Monto inicial</dt><dd class="col-sm-8">$ <?= htmlspecialchars((string) $sesion->monto_inicial, ENT_QUOTES, 'UTF-8') ?></dd>
                <?php if ($sesion->estado === 'CERRADA'): ?>
                    <dt class="col-sm-4">Usuario cierre</dt><dd class="col-sm-8"><?= htmlspecialchars(($sesion->nombre_cierre ?? '') . ' ' . ($sesion->apellido_cierre ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                    <dt class="col-sm-4">Fecha cierre</dt><dd class="col-sm-8"><?= htmlspecialchars($sesion->fecha_cierre, ENT_QUOTES, 'UTF-8') ?></dd>
                    <?php foreach (['total_ventas' => 'Total ventas', 'total_efectivo' => 'Total efectivo', 'efectivo_esperado' => 'Efectivo esperado', 'efectivo_contado' => 'Efectivo contado', 'diferencia' => 'Diferencia'] as $campo => $etiqueta): ?>
                        <dt class="col-sm-4"><?= htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') ?></dt><dd class="col-sm-8">$ <?= htmlspecialchars((string) $sesion->$campo, ENT_QUOTES, 'UTF-8') ?></dd>
                    <?php endforeach; ?>
                    <dt class="col-sm-4">Observación cierre</dt><dd class="col-sm-8"><?= htmlspecialchars($sesion->observacion_cierre ?? '', ENT_QUOTES, 'UTF-8') ?></dd>
                <?php endif; ?>
            </dl>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
