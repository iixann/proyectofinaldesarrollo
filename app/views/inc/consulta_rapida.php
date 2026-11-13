<?php
$rolesAtajos = $_SESSION['usuario']['roles'];
$administrarAtajos = (bool) array_intersect(['ADMINISTRADOR', 'DUEÑO'], $rolesAtajos);
$cajaAtajos = $administrarAtajos || in_array('VENDEDOR', $rolesAtajos, true);
$stockAtajos = $administrarAtajos || in_array('RECEPCIONISTA', $rolesAtajos, true);
?>
<div class="container small text-secondary pb-2" id="atajosGlobales">
    <?php if ($cajaAtajos): ?><a class="text-secondary me-2" data-atajo="p" href="/proyectofinaldesarrollo/pos/index">Alt+P POS</a><?php endif; ?>
    <button type="button" class="btn btn-link btn-sm text-secondary p-0 me-2" id="abrirConsultaRapida">Alt+Q Consultar</button>
    <?php if ($stockAtajos): ?>
    <a class="text-secondary me-2" data-atajo="s" href="/proyectofinaldesarrollo/stock/index">Alt+S Stock</a>
    <a class="text-secondary me-2" data-atajo="r" href="/proyectofinaldesarrollo/reposicion/index">Alt+R Reposición</a>
    <?php endif; ?>
    <?php if ($cajaAtajos): ?><a class="text-secondary" data-atajo="v" href="/proyectofinaldesarrollo/venta/index">Alt+V Ventas</a><?php endif; ?>
</div>
<div class="modal fade" id="consultaRapida" tabindex="-1" aria-labelledby="tituloConsultaRapida" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2">
            <h2 class="modal-title fs-5" id="tituloConsultaRapida">Consultar producto / precio</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar consulta"></button>
        </div>
        <div class="modal-body">
            <p class="small text-secondary">Solo consulta · No agrega a la venta · Esc para cerrar</p>
            <form id="formConsultaRapida" action="/proyectofinaldesarrollo/pos/consultarProducto" method="get">
                <label class="form-label" for="eanConsultaRapida">Código EAN</label>
                <div class="input-group">
                    <input class="form-control" id="eanConsultaRapida" name="ean" maxlength="50" autocomplete="off" required placeholder="Escanear y presionar Enter">
                    <button class="btn btn-primary" type="submit">Consultar</button>
                </div>
            </form>
            <div id="resultadoConsultaRapida" class="mt-3" role="status" aria-live="polite" aria-atomic="true"></div>
        </div>
    </div></div>
</div>
