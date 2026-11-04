<!DOCTYPE html>
<html lang="es">
<head><title>Recepcionar compra | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'recepciones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a href="/proyectofinaldesarrollo/recepcion/index">Volver a Recepciones</a>
        <h1 class="h2 fw-bold my-4">Recepcionar Compra #<?= (int) $compra->id_compra ?></h1>
        <p class="mb-2"><span class="badge bg-primary-subtle text-primary-emphasis">Origen: Compra</span></p>
        <p><strong>Proveedor:</strong> <?= htmlspecialchars($compra->razon_social, ENT_QUOTES, 'UTF-8') ?> · <strong>Fecha:</strong> <?= htmlspecialchars($compra->fecha_compra, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="alert alert-info">Revisá físicamente la mercadería e ingresá lo recibido. Para aceptar, todas las cantidades deben coincidir. Si hay diferencias o daños, rechazá e indicá el motivo; no se incorporará stock.</p>
        <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <form method="post" action="/proyectofinaldesarrollo/recepcion/guardarCompra/<?= (int) $compra->id_compra ?>">
                <input type="hidden" name="csrf_recepcion" value="<?= htmlspecialchars($tokenRecepcion, ENT_QUOTES, 'UTF-8') ?>">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p><table class="table align-middle tabla-recepcion"><thead><tr><th>Producto</th><th>Código EAN</th><th>Cantidad esperada</th><th>Cantidad recibida *</th></tr></thead><tbody>
                <?php foreach ($detalles as $detalle): ?><tr>
                    <td><?= htmlspecialchars($detalle->nombre_producto, ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($detalle->codigo_barras, ENT_QUOTES, 'UTF-8') ?></td><td class="cantidad-esperada"><?= (int) $detalle->cantidad ?></td>
                    <td><input class="form-control" type="number" min="0" max="2147483647" step="1" name="cantidades[<?= (int) $detalle->id_producto ?>]" aria-label="Cantidad recibida de <?= htmlspecialchars($detalle->nombre_producto, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(is_string($cantidades[$detalle->id_producto] ?? null) ? $cantidades[$detalle->id_producto] : '', ENT_QUOTES, 'UTF-8') ?>" required></td>
                </tr><?php endforeach; ?></tbody></table></div>
                <div class="mb-3"><label class="form-label" for="observacion">Observación</label><textarea class="form-control" id="observacion" name="observacion" maxlength="255"><?= htmlspecialchars($datos['observacion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
                <div class="mb-4"><label class="form-label" for="motivo_rechazo">Motivo de rechazo (obligatorio si rechazás)</label><textarea class="form-control" id="motivo_rechazo" name="motivo_rechazo" maxlength="255" rows="3" aria-describedby="ayudaRechazo"><?= htmlspecialchars($datos['motivo_rechazo'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea><p class="form-text mb-0" id="ayudaRechazo">Al rechazar se guarda el motivo y no se incorpora stock.</p></div>
                <p class="form-text mb-3">Indicá 0 cuando no llegó ninguna unidad del producto.</p>
                <div class="acciones-formulario d-flex flex-wrap gap-2 border-top pt-3">
                <button class="btn btn-success" type="submit" name="decision" value="ACEPTADA">Aceptar recepción</button>
                <button class="btn btn-outline-danger" type="submit" name="decision" value="RECHAZADA">Rechazar recepción</button>
                <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/recepcion/index">Cancelar</a>
                </div>
            </form>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
