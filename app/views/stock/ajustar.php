<!DOCTYPE html>
<html lang="es">
<head>
    <title>Ajustar stock | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'stock'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Ajustar stock</h1>
        <div class="row"><div class="col-12 col-lg-8"><section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 p-md-5">
            <p class="fw-semibold mb-2"><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-secondary">Stock actual: <?= htmlspecialchars((string) $producto->stock_actual, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/stock/guardarAjuste/<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="AJUSTE_ENTRADA" <?= ($datos['tipo'] ?? '') === 'AJUSTE_ENTRADA' ? 'selected' : '' ?>>Entrada</option>
                        <option value="AJUSTE_SALIDA" <?= ($datos['tipo'] ?? '') === 'AJUSTE_SALIDA' ? 'selected' : '' ?>>Salida</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="cantidad">Cantidad *</label>
                    <input class="form-control" type="number" id="cantidad" name="cantidad" min="1" max="2147483647" step="1" value="<?= htmlspecialchars($datos['cantidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="observacion">Observación</label>
                    <textarea class="form-control" id="observacion" name="observacion" maxlength="255" rows="3"><?= htmlspecialchars($datos['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="d-flex flex-wrap gap-2 border-top pt-4">
                    <button class="btn btn-primary" type="submit">Registrar movimiento</button>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/stock/index">Cancelar</a>
                </div>
            </form>
        </div></section></div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
