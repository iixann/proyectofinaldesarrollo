<!DOCTYPE html>
<html lang="es">
<head><title>Registrar decomiso | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
<?php $seccionActual = 'productos'; require APP_PATH . 'views/inc/navbar.php'; ?>
<main class="container py-4 flex-grow-1" id="contenido">
    <h1 class="h2 mb-3">Registrar decomiso</h1>
    <p><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?> · Stock actual: <?= (int) $producto->stock_actual ?></p>
    <p class="text-secondary">Retira unidades por rotura, vencimiento o daño. El producto sigue en el catálogo.</p>
    <?php foreach ($errores as $error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    <!-- El backend valida el token, el motivo y el stock antes de registrar la salida. -->
    <form class="card card-body" method="post" action="/proyectofinaldesarrollo/stock/guardarDecomiso/<?= (int) $producto->id_producto ?>">
        <input type="hidden" name="csrf_decomiso" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3"><label class="form-label" for="cantidad">Cantidad de unidades *</label>
            <input class="form-control" type="number" id="cantidad" name="cantidad" min="1" max="2147483647" step="1" value="<?= htmlspecialchars($datos['cantidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="mb-3"><label class="form-label" for="motivo">Motivo *</label>
            <textarea class="form-control" id="motivo" name="motivo" maxlength="255" required><?= htmlspecialchars($datos['motivo'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-danger" type="submit">Confirmar decomiso</button>
            <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/producto/index">Cancelar</a></div>
    </form>
</main>
<?php require APP_PATH . 'views/inc/footer.php'; ?>
</body></html>
