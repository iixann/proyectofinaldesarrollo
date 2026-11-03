<!DOCTYPE html>
<html lang="es">
<head>
    <title>Venta #<?= (int) $venta->id_venta ?> | Identidad Singular</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'ventas'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><a href="/proyectofinaldesarrollo/venta/index">Volver a Ventas</a><h1 class="h2 fw-bold mt-3">Venta #<?= (int) $venta->id_venta ?></h1></div>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/venta/ticket/<?= (int) $venta->id_venta ?>">Imprimir / reimprimir ticket</a>
        </div>
        <section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
            <p>Fecha: <?= htmlspecialchars($venta->fecha_venta, ENT_QUOTES, 'UTF-8') ?></p>
            <p>Caja: <?= htmlspecialchars($venta->caja, ENT_QUOTES, 'UTF-8') ?> · Sesión #<?= (int) $venta->id_caja_sesion ?></p>
            <p>Vendedor: <?= htmlspecialchars($venta->nombre . ' ' . $venta->apellido, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mb-0">Estado: <?= (int) $venta->estado === 1 ? 'CONFIRMADA' : 'INACTIVA' ?></p>
        </div></section>
        <section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
            <h2 class="h5">Productos vendidos</h2>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio histórico</th><th>Subtotal</th></tr></thead><tbody>
                <?php foreach ($detalles as $detalle): ?><tr>
                    <td><?= htmlspecialchars($detalle->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $detalle->cantidad ?></td>
                    <td>$ <?= htmlspecialchars((string) $detalle->precio_unitario, ENT_QUOTES, 'UTF-8') ?></td><td>$ <?= htmlspecialchars((string) $detalle->subtotal, ENT_QUOTES, 'UTF-8') ?></td>
                </tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="h4 text-end text-primary">Total: $ <?= htmlspecialchars((string) $venta->total, ENT_QUOTES, 'UTF-8') ?></p>
        </div></section>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <h2 class="h5">Pagos</h2>
            <div class="table-responsive"><table class="table"><thead><tr><th>Medio</th><th>Aplicado</th><th>Entregado</th><th>Vuelto</th><th>Referencia</th></tr></thead><tbody>
                <?php foreach ($pagos as $pago): ?><tr>
                    <td><?= htmlspecialchars($pago->medio, ENT_QUOTES, 'UTF-8') ?></td><td>$ <?= htmlspecialchars((string) $pago->monto, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $pago->monto_entregado === null ? '—' : '$ ' . htmlspecialchars((string) $pago->monto_entregado, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $pago->vuelto === null ? '—' : '$ ' . htmlspecialchars((string) $pago->vuelto, ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($pago->referencia ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                </tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="fw-semibold text-end mb-0">Total pagado: $ <?= htmlspecialchars($totalPagado, ENT_QUOTES, 'UTF-8') ?></p>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
