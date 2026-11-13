<?php $esRecepcionDirecta = $esRecepcionDirecta ?? false; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title><?= $esRecepcionDirecta ? 'Nueva recepción directa' : 'Nueva compra' ?> | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = $esRecepcionDirecta ? 'recepciones' : 'compras'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4"><?= $esRecepcionDirecta ? 'Nueva recepción directa' : 'Nueva compra' ?></h1>
        <p class="alert alert-info"><?= $esRecepcionDirecta ? 'Sin compra previa. Solo Aceptar incorpora stock; Rechazar conserva auditoría sin modificar existencias.' : 'La compra quedará PENDIENTE de recepción. No modifica stock hasta aceptar la mercadería desde Recepción.' ?></p>
        <div id="mensajeCompra" class="alert d-none" role="status" aria-live="polite"></div>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/<?= $esRecepcionDirecta ? 'recepcion/guardarDirecta' : 'compra/guardar' ?>" id="formularioCompra" data-contexto="<?= $esRecepcionDirecta ? 'recepcion' : 'compra' ?>">
                <input type="hidden" name="csrf_compra" value="<?= htmlspecialchars($tokenCompra, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($esRecepcionDirecta): ?><input type="hidden" name="csrf_recepcion" value="<?= htmlspecialchars($tokenRecepcion, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="id_proveedor">Proveedor *</label>
                        <select class="form-select" id="id_proveedor" name="id_proveedor" required>
                            <option value="">Seleccioná un proveedor</option>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <option value="<?= htmlspecialchars((string) $proveedor->id_proveedor, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($datos['id_proveedor'] ?? '') === (string) $proveedor->id_proveedor ? 'selected' : '' ?>><?= htmlspecialchars($proveedor->razon_social, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary btn-sm mt-2" type="button" data-bs-toggle="modal" data-bs-target="#modalProveedorRapido">+ Nuevo proveedor</button>
                    </div>
                    <?php if (!$esRecepcionDirecta): ?><div class="col-md-6">
                        <label class="form-label" for="numero_comprobante">Número de comprobante</label>
                        <input class="form-control" id="numero_comprobante" name="numero_comprobante" maxlength="50" value="<?= htmlspecialchars($datos['numero_comprobante'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <?php endif; ?><div class="col-12">
                        <label class="form-label" for="observacion">Observación</label>
                        <textarea class="form-control" id="observacion" name="observacion" rows="2" maxlength="255"><?= htmlspecialchars($datos['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <p class="alert alert-warning <?= $proveedores && $productos ? 'd-none' : '' ?>" id="avisoMaestros">Necesitás un proveedor y productos activos asociados. Podés crearlos con las altas rápidas.</p>
                <?php if ($esRecepcionDirecta): ?><div class="mb-4"><label class="form-label" for="motivo_rechazo">Motivo de rechazo (obligatorio si rechazás)</label><textarea class="form-control" id="motivo_rechazo" name="motivo_rechazo" maxlength="255"><?= htmlspecialchars($datos['motivo_rechazo'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div><?php endif; ?>
                <h2 class="h5"><?= $esRecepcionDirecta ? 'Productos recibidos' : 'Productos de la compra' ?></h2>
                <p class="text-secondary small"><?= $esRecepcionDirecta ? 'Los productos nuevos se asocian al proveedor elegido. Para productos existentes, las asociaciones se administran desde Productos.' : 'Usá + Nuevo producto para crear uno nuevo o asociar un producto existente mediante su EAN al proveedor elegido.' ?></p>
                <div class="table-responsive mb-3" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table align-middle tabla-renglones">
                    <thead><tr><th scope="col">Producto *</th><th scope="col"><?= $esRecepcionDirecta ? 'Cantidad recibida *' : 'Cantidad *' ?></th><?php if (!$esRecepcionDirecta): ?><th scope="col">Precio unitario *</th><?php endif; ?><th scope="col">Acción</th></tr></thead>
                    <tbody id="renglonesCompra">
                        <?php $renglones = $renglones ?: [['id_producto' => '', 'cantidad' => '', 'precio_unitario' => '']]; ?>
                        <?php foreach ($renglones as $indice => $renglon): ?>
                            <tr>
                                <td>
                                    <select class="form-select" name="renglones[<?= (int) $indice ?>][id_producto]" aria-label="Producto" required>
                                        <option value="">Seleccioná un producto</option>
                                        <?php foreach ($productos as $producto): ?>
                                            <?php if ((string) $producto->id_proveedor !== (string) ($datos['id_proveedor'] ?? '')) { continue; } ?>
                                            <option value="<?= htmlspecialchars((string) $producto->id_producto, ENT_QUOTES, 'UTF-8') ?>" <?= (string) $renglon['id_producto'] === (string) $producto->id_producto ? 'selected' : '' ?>><?= htmlspecialchars($producto->nombre_producto, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-outline-primary btn-sm mt-2" type="button" data-nuevo-producto>+ Nuevo producto</button>
                                </td>
                                <td><input class="form-control" type="number" min="<?= $esRecepcionDirecta ? '0' : '1' ?>" max="2147483647" step="1" name="renglones[<?= (int) $indice ?>][cantidad]" aria-label="Cantidad" value="<?= htmlspecialchars($renglon['cantidad'], ENT_QUOTES, 'UTF-8') ?>" required></td>
                                <?php if (!$esRecepcionDirecta): ?><td><input class="form-control" type="number" min="0" max="9999999999.99" step="0.01" name="renglones[<?= (int) $indice ?>][precio_unitario]" aria-label="Precio unitario" value="<?= htmlspecialchars($renglon['precio_unitario'], ENT_QUOTES, 'UTF-8') ?>" required></td><?php endif; ?>
                                <td><button class="btn btn-outline-danger btn-sm" type="button" data-quitar>Quitar</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <button class="btn btn-outline-primary mb-4" type="button" id="agregarRenglon">Agregar producto</button>
                <?php if (!$esRecepcionDirecta): ?><div class="resumen-importes d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" aria-live="polite">
                    <div><p class="fw-semibold mb-1">Total estimado de la compra</p><p class="small text-secondary mb-0" id="ayudaTotalCompra">El importe definitivo se calcula al registrar la compra.</p></div>
                    <output id="totalCompra" aria-label="Total estimado de la compra">$ 0,00</output>
                </div><?php endif; ?>
                <p class="text-secondary small"><?= $esRecepcionDirecta ? 'No se crea una compra. Para aceptar, todas las cantidades deben ser mayores a 0.' : 'El total se calcula al registrar la compra. El precio de venta de los productos no se modifica.' ?></p>
                <div class="acciones-formulario d-flex flex-wrap gap-2 border-top pt-4">
                    <button class="btn btn-primary" type="submit" <?= $esRecepcionDirecta ? 'name="decision" value="ACEPTADA"' : '' ?> id="registrarCompra" <?= !$proveedores || !$productos ? 'disabled' : '' ?>><?= $esRecepcionDirecta ? 'Aceptar recepción directa' : 'Registrar compra' ?></button>
                    <?php if ($esRecepcionDirecta): ?><button class="btn btn-outline-danger" type="submit" name="decision" value="RECHAZADA" id="rechazarRecepcion" <?= !$proveedores || !$productos ? 'disabled' : '' ?>>Rechazar recepción directa</button><?php endif; ?>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/<?= $esRecepcionDirecta ? 'recepcion' : 'compra' ?>/index">Cancelar</a>
                </div>
            </form>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/compra/altas_rapidas.php'; ?>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <script type="application/json" id="datosProductosCompra"><?= json_encode($productos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    <script src="/proyectofinaldesarrollo/public/js/compra.js?v=<?= substr(hash_file('sha256', __DIR__ . '/../../../public/js/compra.js'), 0, 12) ?>"></script>
</body>
</html>
