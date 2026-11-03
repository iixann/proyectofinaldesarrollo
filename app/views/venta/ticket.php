<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #<?= (int) $venta->id_venta ?> | Identidad Singular</title>
    <style>
        body { font: 12px monospace; color: #000; background: #fff; margin: 16px auto; width: 72mm; }
        h1 { font-size: 18px; margin-bottom: 4px; } h2 { font-size: 14px; }
        header { text-align: center; } p { margin: 6px 0; overflow-wrap: anywhere; }
        table { border-collapse: collapse; width: 100%; font-size: inherit; table-layout: fixed; }
        th, td { padding: 4px 0; vertical-align: top; overflow-wrap: anywhere; }
        .importe { text-align: right; } .separador { border-top: 1px dashed #000; margin-top: 10px; padding-top: 8px; }
        .total { font-size: 16px; font-weight: bold; } .no-imprimir { margin-bottom: 20px; }
        button { padding: 8px; background: #6D1A36; color: white; border: 0; cursor: pointer; }
        @page { margin: 4mm; }
        @media print { body { margin: 0; } .no-imprimir { display: none; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <nav class="no-imprimir"><button type="button" onclick="window.print()">Imprimir ticket</button> <a href="/proyectofinaldesarrollo/venta/ver/<?= (int) $venta->id_venta ?>">Volver a la venta</a></nav>
    <header><h1>Identidad Singular</h1><p>Vinería</p><h2>Ticket de venta #<?= (int) $venta->id_venta ?></h2></header>
    <p>Fecha: <?= htmlspecialchars($venta->fecha_venta, ENT_QUOTES, 'UTF-8') ?></p>
    <p>Caja: <?= htmlspecialchars($venta->caja, ENT_QUOTES, 'UTF-8') ?> · Sesión #<?= (int) $venta->id_caja_sesion ?></p>
    <p>Vendedor: <?= htmlspecialchars($venta->nombre . ' ' . $venta->apellido, ENT_QUOTES, 'UTF-8') ?></p>
    <table class="separador"><thead><tr><th style="width:56%;text-align:left">Producto / Cant. × precio</th><th class="importe">Subtotal</th></tr></thead><tbody>
        <?php foreach ($detalles as $detalle): ?><tr>
            <td><?= htmlspecialchars($detalle->nombre_producto, ENT_QUOTES, 'UTF-8') ?><br><?= (int) $detalle->cantidad ?> × $ <?= htmlspecialchars((string) $detalle->precio_unitario, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="importe">$ <?= htmlspecialchars((string) $detalle->subtotal, ENT_QUOTES, 'UTF-8') ?></td>
        </tr><?php endforeach; ?>
    </tbody></table>
    <p class="total importe separador">TOTAL $ <?= htmlspecialchars((string) $venta->total, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="separador">
        <?php foreach ($pagos as $pago): ?>
            <p><?= htmlspecialchars($pago->medio, ENT_QUOTES, 'UTF-8') ?>: $ <?= htmlspecialchars((string) $pago->monto, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($pago->monto_entregado !== null): ?><p>Entregado: $ <?= htmlspecialchars((string) $pago->monto_entregado, ENT_QUOTES, 'UTF-8') ?> · Vuelto: $ <?= htmlspecialchars((string) $pago->vuelto, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <?php if ($pago->referencia !== null && $pago->referencia !== ''): ?><p>Referencia: <?= htmlspecialchars($pago->referencia, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php endforeach; ?>
        <p class="importe"><strong>Total pagado $ <?= htmlspecialchars($totalPagado, ENT_QUOTES, 'UTF-8') ?></strong></p>
    </div>
    <p class="separador" style="text-align:center">Gracias por tu compra.</p>
</body>
</html>
