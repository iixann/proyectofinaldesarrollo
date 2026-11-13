    <div class="modal fade" id="modalProveedorRapido" tabindex="-1" aria-labelledby="tituloProveedorRapido" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="tituloProveedorRapido">Nuevo proveedor</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <form id="formularioProveedorRapido" method="post" action="/proyectofinaldesarrollo/compra/crearProveedorRapido">
                <div class="modal-body">
                    <p class="alert alert-danger d-none" data-error role="alert"></p>
                    <div class="mb-3"><label class="form-label" for="rapido_razon_social">Razón social *</label><input class="form-control" id="rapido_razon_social" name="razon_social" type="text" maxlength="150" required></div>
                    <div class="mb-3"><label class="form-label" for="rapido_cuit">CUIT</label><input class="form-control" id="rapido_cuit" name="cuit" type="text" maxlength="13" ></div>
                    <div class="mb-3"><label class="form-label" for="rapido_nombre_contacto">Nombre de contacto</label><input class="form-control" id="rapido_nombre_contacto" name="nombre_contacto" type="text" maxlength="100" ></div>
                    <div class="mb-3"><label class="form-label" for="rapido_telefono">Teléfono</label><input class="form-control" id="rapido_telefono" name="telefono" type="text" maxlength="50" ></div>
                    <div class="mb-3"><label class="form-label" for="rapido_email">Email</label><input class="form-control" id="rapido_email" name="email" type="email" maxlength="150" ></div>
                    <div class="mb-3"><label class="form-label" for="rapido_direccion">Dirección</label><input class="form-control" id="rapido_direccion" name="direccion" type="text" maxlength="200" ></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" >Crear proveedor</button></div>
            </form>
        </div></div>
    </div>
    <div class="modal fade" id="modalProductoRapido" tabindex="-1" aria-labelledby="tituloProductoRapido" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="tituloProductoRapido">Nuevo producto</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <form id="formularioProductoRapido" method="post" action="/proyectofinaldesarrollo/compra/crearProductoRapido">
                <div class="modal-body">
                    <p class="alert alert-danger d-none" data-error role="alert"></p>
                    <input type="hidden" name="id_proveedor">
                    <div class="mb-3"><label class="form-label" for="rapido_codigo_barras">Código EAN *</label>
                        <div class="input-group"><input class="form-control" id="rapido_codigo_barras" name="codigo_barras" maxlength="50" required>
                        <?php if (!$esRecepcionDirecta): ?><button class="btn btn-outline-primary" type="button" id="consultarEanCompra">Comprobar EAN</button><?php endif; ?></div>
                        <?php if (!$esRecepcionDirecta): ?><div class="form-text">Ingresá el EAN para comprobar si el producto ya existe antes de completar el alta.</div><?php endif; ?>
                    </div>
                    <?php if (!$esRecepcionDirecta): ?>
                    <div class="alert alert-info" id="productoExistenteCompra" hidden role="status" aria-live="polite">
                        <p class="fw-semibold mb-2">Este producto ya existe.</p>
                        <p class="mb-1">Producto: <strong data-existente-nombre></strong></p>
                        <p class="mb-2">EAN: <span data-existente-ean></span></p>
                        <p class="mb-0" data-existente-mensaje></p>
                    </div>
                    <?php endif; ?>
                    <fieldset data-datos-nuevo-producto>
                    <p class="text-secondary">El producto nuevo se asociará al proveedor seleccionado con stock inicial 0; la recepción aceptada registrará el ingreso.</p>
                    <?php if (!$categorias): ?><p class="alert alert-warning">No hay categorías activas disponibles. Solicitá su alta desde Categorías antes de crear un producto.</p><?php endif; ?>
                    <div class="mb-3"><label class="form-label" for="rapido_nombre_producto">Nombre del producto *</label><input class="form-control" id="rapido_nombre_producto" name="nombre_producto" maxlength="150" required></div>
                    <div class="mb-3"><label class="form-label" for="rapido_id_categoria">Categoría *</label><select class="form-select" id="rapido_id_categoria" name="id_categoria" required>
                        <option value="">Seleccioná una categoría</option>
                        <?php foreach ($categorias as $categoria): ?><option value="<?= (int) $categoria->id_categoria ?>"><?= htmlspecialchars($categoria->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="mb-3"><label class="form-label" for="rapido_precio_venta">Precio de venta *</label><input class="form-control" id="rapido_precio_venta" name="precio_venta" type="number" min="0" max="9999999999.99" step="0.01" required><div class="form-text">No es el precio de compra; este se ingresa en el renglón de la compra.</div></div>
                    <div class="mb-3"><label class="form-label" for="rapido_stock_minimo">Stock mínimo</label><input class="form-control" id="rapido_stock_minimo" name="stock_minimo" type="number" min="0" max="2147483647" step="1" value="0"></div>
                    <div class="mb-3"><label class="form-label" for="rapido_descripcion">Descripción</label><textarea class="form-control" id="rapido_descripcion" name="descripcion" maxlength="255" rows="2"></textarea></div>
                    </fieldset>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" <?= !$categorias ? 'disabled' : '' ?>>Crear producto</button></div>
            </form>
        </div></div>
    </div>
