<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nueva compra | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'compras'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Nueva compra</h1>
        <div id="mensajeCompra" class="alert d-none" role="status" aria-live="polite"></div>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/compra/guardar" id="formularioCompra">
                <input type="hidden" name="csrf_compra" value="<?= htmlspecialchars($tokenCompra, ENT_QUOTES, 'UTF-8') ?>">
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
                    <div class="col-md-6">
                        <label class="form-label" for="numero_comprobante">Número de comprobante</label>
                        <input class="form-control" id="numero_comprobante" name="numero_comprobante" maxlength="50" value="<?= htmlspecialchars($datos['numero_comprobante'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="observacion">Observación</label>
                        <textarea class="form-control" id="observacion" name="observacion" rows="2" maxlength="255"><?= htmlspecialchars($datos['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>
                <p class="alert alert-warning <?= $proveedores && $productos ? 'd-none' : '' ?>" id="avisoMaestros">Necesitás un proveedor y productos activos asociados. Podés crearlos con las altas rápidas.</p>
                <h2 class="h5">Productos de la compra</h2>
                <p class="text-secondary small">Los productos nuevos se asocian al proveedor elegido. Para productos existentes, las asociaciones se administran desde Productos.</p>
                <div class="table-responsive mb-3"><table class="table align-middle">
                    <thead><tr><th scope="col">Producto *</th><th scope="col">Cantidad *</th><th scope="col">Precio unitario *</th><th scope="col">Acción</th></tr></thead>
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
                                <td><input class="form-control" type="number" min="1" max="2147483647" step="1" name="renglones[<?= (int) $indice ?>][cantidad]" aria-label="Cantidad" value="<?= htmlspecialchars($renglon['cantidad'], ENT_QUOTES, 'UTF-8') ?>" required></td>
                                <td><input class="form-control" type="number" min="0" max="9999999999.99" step="0.01" name="renglones[<?= (int) $indice ?>][precio_unitario]" aria-label="Precio unitario" value="<?= htmlspecialchars($renglon['precio_unitario'], ENT_QUOTES, 'UTF-8') ?>" required></td>
                                <td><button class="btn btn-outline-danger btn-sm" type="button" data-quitar>Quitar</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <button class="btn btn-outline-primary mb-4" type="button" id="agregarRenglon">Agregar producto</button>
                <p class="text-secondary small">El total se calcula al registrar la compra. El precio de venta de los productos no se modifica.</p>
                <div class="d-flex flex-wrap gap-2 border-top pt-4">
                    <button class="btn btn-primary" type="submit" id="registrarCompra" <?= !$proveedores || !$productos ? 'disabled' : '' ?>>Registrar compra</button>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/compra/index">Cancelar</a>
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
