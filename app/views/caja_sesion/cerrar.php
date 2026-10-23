<!DOCTYPE html>
<html lang="es">
<head>
    <title>Cerrar caja | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'sesiones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Cerrar caja</h1>
        <div class="row"><div class="col-lg-8"><section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <dl class="row">
                <dt class="col-sm-5">Caja</dt><dd class="col-sm-7"><?= htmlspecialchars($sesion->caja, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-5">Usuario apertura</dt><dd class="col-sm-7"><?= htmlspecialchars($sesion->nombre . ' ' . $sesion->apellido, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-5">Fecha apertura</dt><dd class="col-sm-7"><?= htmlspecialchars($sesion->fecha_apertura, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-5">Monto inicial</dt><dd class="col-sm-7">$ <?= htmlspecialchars((string) $sesion->monto_inicial, ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-5">Total ventas</dt><dd class="col-sm-7">$ <?= htmlspecialchars($resumen['total_ventas'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-5">Total efectivo</dt><dd class="col-sm-7">$ <?= htmlspecialchars($resumen['total_efectivo'], ENT_QUOTES, 'UTF-8') ?></dd>
                <dt class="col-sm-5">Efectivo esperado</dt><dd class="col-sm-7 fw-bold">$ <?= htmlspecialchars($resumen['efectivo_esperado'], ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
            <p class="text-secondary small">Los totales se vuelven a calcular al confirmar el cierre. El cierre guardado no se puede editar.</p>
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/sesioncaja/guardarCierre/<?= htmlspecialchars((string) $sesion->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label" for="efectivo_contado">Efectivo contado *</label>
                    <input class="form-control" type="number" min="0" max="9999999999.99" step="0.01" id="efectivo_contado" name="efectivo_contado" value="<?= htmlspecialchars($datos['efectivo_contado'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="observacion_cierre">Observación</label>
                    <textarea class="form-control" id="observacion_cierre" name="observacion_cierre" rows="3" maxlength="255"><?= htmlspecialchars($datos['observacion_cierre'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="d-flex gap-2 border-top pt-4">
                    <button class="btn btn-primary" type="submit">Confirmar cierre</button>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/sesioncaja/index">Cancelar</a>
                </div>
            </form>
        </div></section></div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
