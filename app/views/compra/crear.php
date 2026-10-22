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
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/compra/guardar" id="formularioCompra">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="id_proveedor">Proveedor *</label>
                        <select class="form-select" id="id_proveedor" name="id_proveedor" required>
                            <option value="">Seleccioná un proveedor</option>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <option value="<?= htmlspecialchars((string) $proveedor->id_proveedor, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($datos['id_proveedor'] ?? '') === (string) $proveedor->id_proveedor ? 'selected' : '' ?>><?= htmlspecialchars($proveedor->razon_social, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
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
                <?php if (!$proveedores || !$productos): ?><p class="alert alert-warning">Para registrar una compra necesitás proveedores y productos activos.</p><?php endif; ?>
                <h2 class="h5">Productos de la compra</h2>
                <p class="text-secondary small">Cada producto debe estar asociado al proveedor elegido. Si falta la asociación, agregala desde la edición del producto antes de comprar.</p>
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
                    <button class="btn btn-primary" type="submit" <?= !$proveedores || !$productos ? 'disabled' : '' ?>>Registrar compra</button>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/compra/index">Cancelar</a>
                </div>
            </form>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <script>
        const productosPorProveedor = <?= json_encode($productos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
        const proveedorCompra = document.getElementById('id_proveedor');
        function actualizarProductos(select) {
            const seleccionado = select.value;
            select.replaceChildren(new Option('Seleccioná un producto', ''));
            productosPorProveedor.forEach(function (producto) {
                if (String(producto.id_proveedor) === proveedorCompra.value) {
                    select.add(new Option(producto.nombre_producto, String(producto.id_producto)));
                }
            });
            select.value = Array.from(select.options).some(function (opcion) { return opcion.value === seleccionado; }) ? seleccionado : '';
        }
        const cuerpoCompra = document.getElementById('renglonesCompra');
        const plantillaRenglon = cuerpoCompra.rows[0].cloneNode(true);
        let siguienteRenglon = cuerpoCompra.rows.length;
        proveedorCompra.addEventListener('change', function () {
            cuerpoCompra.querySelectorAll('select').forEach(actualizarProductos);
        });
        document.getElementById('agregarRenglon').addEventListener('click', function () {
            const fila = plantillaRenglon.cloneNode(true);
            fila.querySelectorAll('[name]').forEach(function (campo) {
                campo.name = campo.name.replace(/renglones\[\d+\]/, 'renglones[' + siguienteRenglon + ']');
                campo.value = '';
            });
            siguienteRenglon++;
            actualizarProductos(fila.querySelector('select'));
            cuerpoCompra.appendChild(fila);
        });
        cuerpoCompra.addEventListener('click', function (evento) {
            if (evento.target.matches('[data-quitar]')) {
                const fila = evento.target.closest('tr');
                if (cuerpoCompra.rows.length > 1) {
                    fila.remove();
                } else {
                    fila.querySelectorAll('[name]').forEach(function (campo) { campo.value = ''; });
                }
            }
        });
    </script>
</body>
</html>
