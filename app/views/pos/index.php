<!DOCTYPE html>
<html lang="es">
<head>
    <title>Punto de Venta | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
    <?php $versionEstiloPos = substr(hash_file('sha256', APP_PATH . '../public/css/pos.css'), 0, 12); ?>
    <link rel="stylesheet" href="/proyectofinaldesarrollo/css/pos.css?v=<?= htmlspecialchars($versionEstiloPos, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'consulta_pos'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container-fluid pos-pantalla py-3 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pos-titulo">
            <h1 class="h3 fw-bold mb-0"><i class="fa-solid fa-cash-register text-primary me-2" aria-hidden="true"></i>Punto de Venta</h1>
            <a class="btn btn-outline-primary" href="/proyectofinaldesarrollo/sesioncaja/index">Sesiones de caja</a>
        </div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-warning" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if (!$sesiones): ?>
            <div class="alert alert-warning" role="alert">No hay una sesión abierta utilizable. Abrí una sesión en una caja activa para operar el POS.</div>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/sesioncaja/abrir">Abrir caja</a>
        <?php else: ?>
            <details class="card border-0 shadow-sm rounded-4 mb-2 pos-selector" <?= !$seleccionada ? 'open' : '' ?>><summary class="px-3 py-2 text-primary small fw-semibold">Seleccionar / cambiar sesión de caja</summary><div class="card-body p-3">
                <form method="get" action="/proyectofinaldesarrollo/pos/index" class="row align-items-end g-3">
                    <div class="col-lg-9">
                        <label class="form-label fw-semibold" for="sesionPos">Caja / sesión abierta *</label>
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
            </div></details>
            <?php if (!$seleccionada): ?>
                <p class="alert alert-info">Seleccioná una sesión abierta para habilitar la búsqueda y el carrito.</p>
            <?php else: ?>
                <div id="terminalPos" data-sesion="<?= htmlspecialchars((string) $seleccionada->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?>" data-token="<?= htmlspecialchars($tokenVenta, ENT_QUOTES, 'UTF-8') ?>">
                    <header class="pos-cabecera mb-3"><span class="badge text-bg-success">Sesión abierta</span> <strong><?= htmlspecialchars($seleccionada->caja, ENT_QUOTES, 'UTF-8') ?></strong> · Sesión #<?= htmlspecialchars((string) $seleccionada->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?> · Vendedor: <?= htmlspecialchars($seleccionada->nombre . ' ' . $seleccionada->apellido, ENT_QUOTES, 'UTF-8') ?></header>
                    <div class="pos-distribucion">
                        <div class="pos-productos">
                            <section class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body p-3">
                                <form action="/proyectofinaldesarrollo/pos/buscar" method="get" id="buscarEan" class="mb-2">
                                    <label class="form-label fw-semibold" for="codigo">Escanear Código EAN</label>
                                    <div class="input-group input-group-lg">
                                        <input class="form-control" type="text" id="codigo" name="codigo" placeholder="Escanear EAN y presionar Enter" maxlength="50" autocomplete="off" required autofocus>
                                        <button class="btn btn-primary" type="submit">Agregar</button>
                                    </div>
                                    <p class="text-secondary small mt-2 mb-0">EAN exacto + Enter para agregar. Para buscar por nombre, usá el campo de abajo.</p>
                                </form>
                                <form action="/proyectofinaldesarrollo/pos/buscar" method="get" id="buscarNombre">
                                    <label class="form-label" for="nombre">Búsqueda manual por nombre *</label>
                                    <div class="input-group">
                                        <input class="form-control" type="text" id="nombre" name="nombre" placeholder="Buscar producto por nombre" maxlength="150" autocomplete="off" required>
                                        <button class="btn btn-outline-primary" type="submit">Buscar</button>
                                    </div>
                                </form>
                            </div></section>
                            <a id="verUltimaVenta" class="btn btn-outline-primary mb-3" hidden>Ver venta / imprimir ticket</a>
                            <p id="mensajePos" role="status" aria-live="polite" class="alert alert-info">Escaneá un producto o buscá por nombre.</p>
                            <section class="card border-0 shadow-sm rounded-4" id="resultadosBusqueda" hidden><div class="card-body p-3">
                                <h2 class="h5">Resultados</h2>
                                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col">EAN</th><th scope="col">Precio</th><th scope="col">Stock</th><th scope="col">Acción</th></tr></thead>
                                    <tbody id="productosEncontrados"></tbody>
                                </table></div>
                            </div></section>

                            <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <h2 class="h4 mb-0">Productos de la venta</h2>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="vaciarCarrito">Vaciar carrito</button>
                                </div>
                                <p class="text-secondary text-center py-4" id="carritoVacio">Carrito vacío</p>
                                <div class="table-responsive" id="tablaCarrito" hidden tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table align-middle">
                                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Cantidad</th><th scope="col">Precio</th><th scope="col">Subtotal</th><th scope="col">Acción</th></tr></thead>
                                    <tbody id="lineasCarrito"></tbody>
                                </table></div>
                            </div></section>
                        </div>
                        <aside class="pos-cobro"><section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3">
                                <div class="pos-total mb-2">
                                    <p class="text-secondary fw-semibold mb-1">TOTAL</p>
                                    <p class="display-6 fw-bold text-primary" id="totalCarrito">$ 0,00</p><p id="cantidadArticulos" class="mb-0">0 artículos</p>
                                </div>
                                <section class="border-top pt-2" aria-labelledby="tituloCobro">
                                    <h3 class="visually-hidden" id="tituloCobro">Cobro</h3>
                                    <div class="pos-opciones-pago mb-2" role="group" aria-label="Elegir medio de pago">
                                        <?php foreach ($mediosPago as $medio): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-elegir-medio="<?= htmlspecialchars((string) $medio->id_medio_pago, ENT_QUOTES, 'UTF-8') ?>" aria-controls="pago-<?= htmlspecialchars((string) $medio->id_medio_pago, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false"><?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!$mediosPago): ?><p class="alert alert-warning">No hay medios de pago activos.</p><?php endif; ?>
                                    <div id="filasPago" class="pos-medios">
                                            <?php foreach ($mediosPago as $medio): ?>
                                                <div class="pos-medio" hidden id="pago-<?= htmlspecialchars((string) $medio->id_medio_pago, ENT_QUOTES, 'UTF-8') ?>" data-nombre="<?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?>" data-medio="<?= htmlspecialchars((string) $medio->id_medio_pago, ENT_QUOTES, 'UTF-8') ?>" data-efectivo="<?= $medio->nombre === 'EFECTIVO' ? '1' : '0' ?>">
                                                    <h4 class="h6 fw-bold"><?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?></h4>
                                                    <label class="form-label small">Monto aplicado<input class="form-control form-control-sm" type="number" min="0" max="9999999999.99" step="0.01" data-monto aria-label="Monto aplicado <?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?>"></label>
                                                    <div class="pos-detalle-pago">
                                                        <?php if ($medio->nombre === 'EFECTIVO'): ?>
                                                            <label class="form-label small">Entrega cliente
                                                                <input class="form-control form-control-sm" type="number" min="0" max="9999999999.99" step="0.01" data-entregado aria-label="Efectivo entregado">
                                                            </label>

                                                        <?php else: ?>
                                                            <input class="form-control form-control-sm" type="text" maxlength="150" data-referencia placeholder="Referencia opcional" aria-label="Referencia <?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($medio->nombre === 'EFECTIVO'): ?><strong class="pos-vuelto d-block" data-vuelto>Vuelto: $ 0,00</strong><?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                    </div>
                                    <ul class="pos-resumen-pagos" id="resumenPagos" aria-label="Pagos aplicados"></ul>
                                    <p class="mb-1">Total aplicado: <strong id="totalPagado">$ 0,00</strong></p>
                                    <p class="small" id="estadoPagos" role="status">Agregá productos para iniciar el cobro.</p>
                                    <button class="btn btn-primary btn-lg w-100" type="button" id="confirmarVenta" disabled>COBRAR</button>
                                    <details class="text-secondary small mt-2"><summary>Antes de cobrar</summary><p class="mb-0">Al confirmar se registra la venta y se descuenta stock. Si cambiaron precios o existencias, revisá los productos antes de reintentar. El carrito se pierde al recargar.</p></details>
                                </section>
                            </div></section>
                        </aside>
                    </div>
                    <p class="pos-atajos small text-secondary mt-3">F2 EAN · F3 Buscar por nombre · F4 Efectivo · F5 Débito · F6 Crédito · F7 Transferencia / Mercado Pago · F8 Cobrar · Supr Quitar línea · Esc EAN</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <?php
    // Cambiar la URL con el contenido evita mezclar una vista nueva con JS anterior en caché.
    $versionPos = substr(hash_file('sha256', APP_PATH . '../public/js/pos.js'), 0, 12);
    ?>
    <script src="/proyectofinaldesarrollo/js/pos.js?v=<?= htmlspecialchars($versionPos, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
