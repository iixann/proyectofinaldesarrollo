<!DOCTYPE html>
<html lang="es">
<head>
    <title>Punto de Venta | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
    <?php $versionEstiloPos = substr(hash_file('sha256', APP_PATH . '../public/css/pos.css'), 0, 12); ?>
    <link rel="stylesheet" href="/proyectofinaldesarrollo/css/pos.css?v=<?= htmlspecialchars($versionEstiloPos, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="d-flex flex-column min-vh-100 pos-body">
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
                    <header class="pos-cabecera mb-2"><span class="badge text-bg-success">Sesión abierta</span> <strong><?= htmlspecialchars($seleccionada->caja, ENT_QUOTES, 'UTF-8') ?></strong> · Sesión #<?= htmlspecialchars((string) $seleccionada->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?> · Vendedor: <?= htmlspecialchars($seleccionada->nombre . ' ' . $seleccionada->apellido, ENT_QUOTES, 'UTF-8') ?></header>
                    <div class="pos-distribucion">
                        <div class="pos-productos">
                            <section class="pos-busquedas" aria-label="Agregar productos">
                                <form action="/proyectofinaldesarrollo/pos/buscar" method="get" id="buscarEan">
                                    <label class="form-label small mb-1" for="codigo">EAN <kbd>F2</kbd></label>
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" type="text" id="codigo" name="codigo" placeholder="Escanear + Enter" maxlength="50" autocomplete="off" required autofocus>
                                        <button class="btn btn-primary" type="submit">Agregar</button>
                                    </div>
                                </form>
                                <form action="/proyectofinaldesarrollo/pos/buscar" method="get" id="buscarNombre">
                                    <label class="form-label small mb-1" for="nombre">Buscar por nombre <kbd>F3</kbd></label>
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" type="text" id="nombre" name="nombre" placeholder="Nombre del producto" maxlength="150" autocomplete="off" required>
                                        <button class="btn btn-outline-primary" type="submit">Buscar</button>
                                    </div>
                                </form>
                            </section>
                            <p id="avisoCambioPagos" class="small text-warning-emphasis mb-1" role="status" hidden>El total cambió. Revisá los medios de pago.</p>
                            <div class="pos-feedback">
                                <p id="mensajePos" role="status" aria-live="polite" class="alert alert-info">Escaneá un producto o buscá por nombre.</p>
                                <a id="verUltimaVenta" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" hidden>Ticket / Imprimir · F10</a>
                            </div>
                            <section class="card border-0 shadow-sm rounded-4" id="resultadosBusqueda" hidden><div class="card-body p-3">
                                <h2 class="h5">Resultados</h2>
                                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Categoría</th><th scope="col">EAN</th><th scope="col">Precio</th><th scope="col">Stock</th><th scope="col">Acción</th></tr></thead>
                                    <tbody id="productosEncontrados"></tbody>
                                </table></div>
                            </div></section>

                            <section class="card border-0 shadow-sm rounded-4 pos-ticket"><div class="card-body p-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <h2 class="h6 mb-0">Armado de venta</h2>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" id="vaciarCarrito">Vaciar carrito</button>
                                </div>
                                <p class="text-secondary text-center py-4" id="carritoVacio">Carrito vacío</p>
                                <div class="table-responsive" id="tablaCarrito" hidden tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table align-middle">
                                    <thead class="table-light"><tr><th scope="col">Producto</th><th scope="col">Cant.</th><th scope="col">Precio unitario</th><th scope="col">Subtotal</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr></thead>
                                    <tbody id="lineasCarrito"></tbody>
                                </table></div>
                                <div class="pos-total-venta">
                                    <span id="cantidadArticulos">0 artículos</span>
                                    <div class="pos-subtotal"><span>SUBTOTAL</span><strong id="totalCarrito">$ 0,00</strong></div>
                                    <button class="btn btn-primary btn-lg" type="button" id="abrirCobro" disabled>COBRAR <small>F8</small></button>
                                </div>
                            </div></section>
                        </div>
                    </div>
                    <div class="modal" id="cobroPos" tabindex="-1" aria-labelledby="tituloCobro" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                            <div class="modal-header py-2">
                                <h2 class="modal-title fs-5" id="tituloCobro">Cobro</h2>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="volverVenta">← Volver a la venta <small>Esc</small></button>
                            </div>
                            <div class="modal-body">
                                <p id="mensajeCobro" class="alert alert-warning small py-2" role="alert" hidden></p>
                                <div class="pos-total mb-3"><div><span>TOTAL</span><strong id="totalCobro">$ 0,00</strong></div><span id="articulosCobro">0 artículos</span></div>
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
                                                    <?php if ($medio->nombre === 'EFECTIVO'): ?>
                                                        <input type="hidden" data-monto value="0.00">
                                                    <?php else: ?>
                                                        <label class="form-label small">Monto aplicado<input class="form-control form-control-sm" type="number" min="0" max="9999999999.99" step="0.01" data-monto aria-label="Monto aplicado <?= htmlspecialchars($medio->nombre, ENT_QUOTES, 'UTF-8') ?>"></label>
                                                    <?php endif; ?>
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
                                    <div class="pos-balance"><span>APLICADO <strong id="totalPagado">$ 0,00</strong></span><span>FALTA <strong id="faltaPagar">$ 0,00</strong></span></div>
                                    <p class="small" id="estadoPagos" role="status">Agregá productos para iniciar el cobro.</p>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-primary btn-lg w-100" type="button" id="confirmarVenta" disabled>CONFIRMAR VENTA · F8</button>
                            </div>
                        </div></div>
                    </div>
                    <p class="pos-atajos small text-secondary mt-3">F2 EAN · F3 Nombre · F4 Efectivo · F5 Débito · F6 Crédito · F7 Transferencia / MP · F8 Cobro / Confirmar · F9 Consultar · F10 Ticket · Supr Quitar · Esc Volver / EAN</p>
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
