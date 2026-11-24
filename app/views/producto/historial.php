<!DOCTYPE html>
<html lang="es">
<head><title>Auditoría del producto | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
<?php $seccionActual = 'productos'; require APP_PATH . 'views/inc/navbar.php'; ?>
<main class="container py-4 flex-grow-1" id="contenido">
    <h1 class="h2">Auditoría del producto</h1>
    <p><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/producto/index">Volver a Productos</a>
        <a class="btn btn-outline-secondary btn-sm" href="/proyectofinaldesarrollo/stock/movimientos/<?= (int) $producto->id_producto ?>">Movimientos y decomisos</a></div>
    <!-- La auditoría administrativa no modifica ni reemplaza el historial físico de stock. -->
    <p class="small text-secondary">Últimas 100 operaciones registradas desde la instalación de la auditoría.</p>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th></tr></thead>
        <tbody><?php foreach ($operaciones as $operacion): ?><tr>
            <td><?= htmlspecialchars($operacion->fecha, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($operacion->nombre . ' ' . $operacion->apellido, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($operacion->accion, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($operacion->detalle, ENT_QUOTES, 'UTF-8') ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php if (!$operaciones): ?><p>No hay operaciones registradas.</p><?php endif; ?>
</main>
<?php require APP_PATH . 'views/inc/footer.php'; ?>
</body></html>
