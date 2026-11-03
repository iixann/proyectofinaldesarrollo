<!DOCTYPE html>
<html lang="es">
<head>
    <title>Punto de Venta | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'consulta_pos'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <h1 class="h2 fw-bold mb-0"><i class="fa-solid fa-cash-register text-primary me-2" aria-hidden="true"></i>Punto de Venta</h1>
            <a class="btn btn-outline-primary" href="/proyectofinaldesarrollo/sesioncaja/index">Sesiones de caja</a>
        </div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-warning" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if (!$sesiones): ?>
            <div class="alert alert-warning" role="alert">No hay una sesión abierta utilizable. Abrí una sesión en una caja activa para operar el POS.</div>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/sesioncaja/abrir">Abrir caja</a>
        <?php else: ?>
            <section class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body p-4">
                <form method="get" action="/proyectofinaldesarrollo/pos/index" class="row align-items-end g-3">
                    <div class="col-lg-9">
                        <label class="form-label fw-semibold" for="sesionPos">Caja / sesión abierta</label>
                        <select class="form-select" name="sesion" id="sesionPos" required>
                            <option value="">Seleccioná una sesión para operar</option>
                            <?php foreach ($sesiones as $sesion): ?>
                                <option value="<?= htmlspecialchars((string) $sesion->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?>" <?= $seleccionada && (string) $seleccionada->id_caja_sesion === (string) $sesion->id_caja_sesion ? 'selected' : '' ?>><?= htmlspecialchars($sesion->caja . ' — Sesión #' . $sesion->id_caja_sesion . ' — Apertura: ' . $sesion->nombre . ' ' . $sesion->apellido, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3"><button class="btn btn-outline-primary w-100" type="submit" id="elegirSesion">Usar sesión</button></div>
                </form>
                <p class="text-secondary small mb-0 mt-2" id="avisoSesion">Elegí explícitamente la sesión si hay más de una abierta.</p>
            </div></section>
            <?php if (!$seleccionada): ?>
                <p class="alert alert-info">Seleccioná una sesión abierta para habilitar la búsqueda y el carrito.</p>
            <?php else: ?>
                <div id="terminalPos" data-sesion="<?= htmlspecialchars((string) $seleccionada->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?>" data-token="<?= htmlspecialchars($tokenVenta, ENT_QUOTES, 'UTF-8') ?>">
                    <p class="text-primary fw-semibold">Operando en <?= htmlspecialchars($seleccionada->caja, ENT_QUOTES, 'UTF-8') ?> · Sesión #<?= htmlspecialchars((string) $seleccionada->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="row g-4">
                        <div class="col-12 col-xl-6">
                            <section class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-4">
                                <form action="/proyectofinaldesarrollo/pos/buscar" method="get" id="buscarEan" class="mb-4">
                                    <label class="form-label fw-semibold" for="codigo">Código EAN</label>
                                    <div class="input-group input-group-lg">
                                        <input class="form-control" type="text" id="codigo" name="codigo" maxlength="50" autocomplete="off" required autofocus>
                                        <button class="btn btn-primary" type="submit">Agregar</button>
                                    </div>
                                    <p class="text-secondary small mt-2 mb-0">Escaneá o escribí el EAN y presioná Enter.</p>
                                </form>
                                <form action="/proyectofinaldesarrollo/pos/buscar" method="get" id="buscarNombre">
                                    <label class="form-label" for="nombre">Búsqueda manual por nombre</label>
                                    <div class="input-group">
                                        <input class="form-control" type="text" id="nombre" name="nombre" maxlength="150" required>
                                        <button class="btn btn-outline-primary" type="submit">Buscar</button>
                                    </div>
                                </form>
                            </div></section>
                            <p id="mensajePos" role="status" aria-live="polite" class="alert alert-info">Escaneá un producto o buscá por nombre.</p>
                            <section class="card border-0 shadow-sm rounded-4" id="resultadosBusqueda" hidden><div class="card-body p-3">
                                <h2 class="h5">Resultados</h2>
                                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col">EAN</th><th scope="col">Precio</th><th scope="col">Stock</th><th scope="col">Acción</th></tr></thead>
                                    <tbody id="productosEncontrados"></tbody>
                                </table></div>
                            </div></section>
                        </div>
                        <div class="col-12 col-xl-6">
                            <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                    <h2 class="h4 mb-0">Carrito</h2>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="vaciarCarrito">Vaciar carrito</button>
                                </div>
                                <p class="text-secondary text-center py-4" id="carritoVacio">Carrito vacío</p>
                                <div class="table-responsive" id="tablaCarrito" hidden><table class="table align-middle">
                                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Cantidad</th><th scope="col">Precio</th><th scope="col">Subtotal</th><th scope="col">Acción</th></tr></thead>
                                    <tbody id="lineasCarrito"></tbody>
                                </table></div>
                                <div class="border-top pt-4 mt-3 text-end">
                                    <p class="text-secondary fw-semibold mb-1">TOTAL</p>
                                    <p class="display-6 fw-bold text-primary" id="totalCarrito">$ 0,00</p>
                                </div>
                                <section class="border-top pt-3" aria-labelledby="tituloCobro">
                                    <h3 class="h5" id="tituloCobro">Cobro</h3>
                                    <p class="text-secondary small">Distribuí el total entre uno o varios medios. Dejá vacíos los que no uses.</p>
                                    <?php if (!$mediosPago): ?><p class="alert alert-warning">No hay medios de pago activos.</p><?php endif; ?>
                                    <div class="table-responsive"><table class="table align-middle">
                                        <thead><tr><th scope="col">Medio</th><th scope="col">Monto aplicado</th><th scope="col">Entregado / referencia</th></tr></thead>
                                        <tbody id="filasPago">
                                            <?php foreach ($mediosPago as $medio): ?>
                                                <tr data-medio="<?= htmlspecialchars((string) $medio->id_medio_pago, ENT_QUOTES, 'UTF-8') ?>" data-efectivo="<?= $medio->nombre === 'EFECTIVO' ? '1' : '0' ?>">
                                                    <td><?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><input class="form-control form-control-sm" type="number" min="0" max="9999999999.99" step="0.01" data-monto aria-label="Monto aplicado <?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?>"></td>
                                                    <td>
                                                        <?php if ($medio->nombre === 'EFECTIVO'): ?>
                                                            <label class="form-label small">Entregado
                                                                <input class="form-control form-control-sm" type="number" min="0" max="9999999999.99" step="0.01" data-entregado aria-label="Efectivo entregado">
                                                            </label>
                                                            <small class="d-block text-secondary" data-vuelto>Vuelto: $ 0,00</small>
                                                        <?php else: ?>
                                                            <input class="form-control form-control-sm" type="text" maxlength="150" data-referencia placeholder="Referencia opcional" aria-label="Referencia <?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table></div>
                                    <p class="mb-1">Total aplicado: <strong id="totalPagado">$ 0,00</strong></p>
                                    <p class="small" id="estadoPagos" role="status">Agregá productos para iniciar el cobro.</p>
                                    <button class="btn btn-primary btn-lg w-100" type="button" id="confirmarVenta" disabled>Confirmar venta</button>
                                    <p class="text-secondary small mt-2 mb-0">Al confirmar se registra la venta y se descuenta stock. Precios, existencias y total se validan nuevamente en servidor. El carrito y los pagos son temporales hasta confirmar.</p>
                                </section>
                            </div></section>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <script src="/proyectofinaldesarrollo/js/pos.js" defer></script>
</body>
</html>
