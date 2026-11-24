<!DOCTYPE html>
<html lang="es">
<head><title>Rectificar venta | Identidad Singular</title><?php require APP_PATH . 'views/inc/head.php'; ?></head>
<body class="d-flex flex-column min-vh-100">
<?php $seccionActual='ventas'; require APP_PATH . 'views/inc/navbar.php'; ?>
<main class="container py-4 flex-grow-1" id="contenido">
    <h1 class="h2">Rectificar venta #<?= (int) $venta->id_venta ?></h1>
    <p class="fw-semibold">Total original: $ <?= htmlspecialchars((string) $venta->total, ENT_QUOTES, 'UTF-8') ?>. Revisá la diferencia a cobrar o devolver al reemplazar los pagos.</p>
    <p class="alert alert-info">Solo se admite con la caja ABIERTA. La venta original se conserva; sus efectos se revierten y la nueva venta se registra en una sola transacción. Se aplican precios actuales.</p>
    <?php if ($errores): ?><div class="alert alert-danger"><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php
    // Escapa también los valores inválidos devueltos por un formulario rechazado.
    $texto = static fn($valor) => is_scalar($valor) ? htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') : '';
    // Reutiliza la misma fila para los productos existentes y las nuevas líneas.
    $fila = static function ($indice, $renglon) use ($productos, $texto) {
        $renglon = is_array($renglon) ? $renglon : [];
    ?>
    <tr><td><select class="form-select" name="renglones[<?= $texto($indice) ?>][id_producto]" aria-label="Producto" required>
        <option value="">Seleccioná un producto activo</option>
        <?php foreach ($productos as $producto): ?><option value="<?= (int) $producto->id_producto ?>" data-precio="<?= $texto($producto->precio_venta) ?>" <?= (string) $producto->id_producto === $texto($renglon['id_producto'] ?? '') ? 'selected' : '' ?>><?= $texto($producto->nombre_producto) ?></option><?php endforeach; ?>
    </select></td><td><input class="form-control" type="number" min="1" max="2147483647" step="1" name="renglones[<?= $texto($indice) ?>][cantidad]" value="<?= $texto($renglon['cantidad'] ?? '') ?>" aria-label="Cantidad" required></td>
    <td><button type="button" class="btn btn-outline-danger" data-quitar-rectificacion>Quitar</button></td></tr>
    <?php }; ?>
    <!-- Se envía la corrección completa; ningún campo actualiza stock directamente. -->
    <form method="post" action="/proyectofinaldesarrollo/venta/guardarRectificacion/<?= (int) $venta->id_venta ?>" id="formRectificacion">
        <input type="hidden" name="csrf_rectificacion" value="<?= $texto($tokenRectificacion) ?>">
        <div class="mb-3"><label class="form-label" for="motivoRectificacion">Motivo de la corrección *</label><textarea class="form-control" id="motivoRectificacion" name="motivo" maxlength="255" required><?= $texto($motivo) ?></textarea></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Acción</th></tr></thead><tbody id="renglonesRectificacion">
            <?php foreach ($renglones as $indice=>$renglon) $fila($indice, $renglon); ?>
        </tbody></table></div>
        <button class="btn btn-outline-primary mb-3" type="button" id="agregarRectificacion">Agregar producto</button>
        <template id="plantillaRectificacion"><?php $fila('__INDICE__', []); ?></template>
        <p class="h4" role="status">Nuevo total estimado: <output id="totalRectificacion"></output></p>
        <p class="text-secondary">Revisá los medios y montos. Los pagos deben cubrir exactamente el nuevo total. Solo el efectivo admite entrega y vuelto.</p>
        <!-- Conserva los pagos originales como referencia y prepara los nuevos. -->
        <?php foreach ($medios as $indice=>$medio): ?>
        <?php $entrada=[]; foreach ($entradasPago as $pago) if (is_array($pago) && $texto($pago['id_medio_pago'] ?? '') === (string) $medio->id_medio_pago) { $entrada=$pago; break; } ?>
        <fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto fs-6"><?= $texto($medio->nombre) ?></legend>
            <input type="hidden" name="pagos[<?= $indice ?>][id_medio_pago]" value="<?= (int) $medio->id_medio_pago ?>">
            <div class="row g-3"><div class="col-sm-4"><label class="form-label" for="monto_<?= $indice ?>">Monto aplicado</label><input class="form-control" id="monto_<?= $indice ?>" name="pagos[<?= $indice ?>][monto]" type="number" min="0" max="9999999999.99" step="0.01" value="<?= $texto($entrada['monto'] ?? '') ?>"></div>
            <?php if ($medio->nombre === 'EFECTIVO'): ?><div class="col-sm-4"><label class="form-label" for="entrega_<?= $indice ?>">Efectivo entregado</label><input class="form-control" id="entrega_<?= $indice ?>" name="pagos[<?= $indice ?>][monto_entregado]" type="number" min="0" max="9999999999.99" step="0.01" value="<?= $texto($entrada['monto_entregado'] ?? '') ?>"></div><?php endif; ?>
            <div class="col-sm-4"><label class="form-label" for="referencia_<?= $indice ?>">Referencia</label><input class="form-control" id="referencia_<?= $indice ?>" name="pagos[<?= $indice ?>][referencia]" maxlength="150" value="<?= $texto($entrada['referencia'] ?? '') ?>"></div></div>
        </fieldset>
        <?php endforeach; ?>
        <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit">Confirmar rectificación</button><a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/venta/ver/<?= (int) $venta->id_venta ?>">Cancelar</a></div>
    </form>
</main>
<?php require APP_PATH . 'views/inc/footer.php'; ?>
<script src="/proyectofinaldesarrollo/js/rectificar-venta.js?v=<?= substr(hash_file('sha256', 'public/js/rectificar-venta.js'),0,12) ?>" defer></script>
</body></html>
