<!DOCTYPE html>
<html lang="es">
<head>
    <title>Ventas | Identidad Singular</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'ventas'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <h1 class="h2 fw-bold">Ventas</h1>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/pos/index">Ir al Punto de Venta</a>
        </div>
        <form method="get" action="/proyectofinaldesarrollo/venta/index" class="filtros-listado row g-3 align-items-end mb-4">
            <div class="col-sm-4"><label class="form-label" for="id_venta">Número de venta</label><input class="form-control" type="number" id="id_venta" name="id_venta" maxlength="10" min="1" max="4294967295" step="1" value="<?= htmlspecialchars((string) $filtros['id_venta'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-sm-4"><label class="form-label" for="desde">Desde</label><input class="form-control" type="date" id="desde" name="desde" value="<?= htmlspecialchars($filtros['desde'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-sm-4"><label class="form-label" for="hasta">Hasta</label><input class="form-control" type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($filtros['hasta'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-sm-4"><button class="btn btn-outline-primary" type="submit">Buscar</button> <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/venta/index">Limpiar</a></div>
        </form>
        <?php if ($error !== ''): ?><p class="alert alert-warning" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <p class="text-secondary small">Hasta 100 ventas más recientes del período seleccionado. Las ventas confirmadas son de solo lectura.</p>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$ventas): ?><p class="text-secondary text-center my-4">No se encontraron ventas para los filtros seleccionados.</p><?php else: ?>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Fecha</th><th>Caja / sesión</th><th>Vendedor</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody><?php foreach ($ventas as $venta): ?><tr>
                        <td><?= htmlspecialchars((string) $venta->id_venta, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($venta->fecha_venta, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($venta->caja . ' / #' . $venta->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($venta->nombre . ' ' . $venta->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>$ <?= htmlspecialchars((string) $venta->total, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge <?= (int) $venta->estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int) $venta->estado === 1 ? 'CONFIRMADA' : 'INACTIVA' ?></span></td>
                        <td><div class="acciones-tabla d-flex flex-wrap gap-2"><a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/venta/ver/<?= (int) $venta->id_venta ?>">Ver</a> <a class="btn btn-outline-secondary btn-sm" href="/proyectofinaldesarrollo/venta/ticket/<?= (int) $venta->id_venta ?>">Ticket</a></div></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
            <?php endif; ?>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
