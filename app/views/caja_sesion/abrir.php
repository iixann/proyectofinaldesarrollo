<!DOCTYPE html>
<html lang="es">
<head>
    <title>Abrir caja | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'sesiones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Abrir caja</h1>
        <div class="row"><div class="col-lg-8"><section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <?php if (!$cajas): ?><p class="alert alert-warning">No hay cajas activas disponibles.</p><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/sesioncaja/guardarApertura">
                <div class="mb-3">
                    <label class="form-label" for="id_caja">Caja *</label>
                    <select class="form-select" id="id_caja" name="id_caja" required>
                        <option value="">Seleccioná una caja activa</option>
                        <?php foreach ($cajas as $caja): ?>
                            <option value="<?= htmlspecialchars((string) $caja->id_caja, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($datos['id_caja'] ?? '') === (string) $caja->id_caja ? 'selected' : '' ?>><?= htmlspecialchars($caja->nombre, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="monto_inicial">Monto inicial *</label>
                    <input class="form-control" type="number" min="0" max="9999999999.99" step="0.01" id="monto_inicial" name="monto_inicial" value="<?= htmlspecialchars($datos['monto_inicial'] ?? '0.00', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="d-flex gap-2 border-top pt-4">
                    <button class="btn btn-primary" type="submit" <?= !$cajas ? 'disabled' : '' ?>>Abrir caja</button>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/sesioncaja/index">Cancelar</a>
                </div>
            </form>
        </div></section></div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
