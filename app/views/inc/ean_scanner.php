<!-- Lector compartido: solo entrega un EAN al módulo que lo abrió. -->
<link rel="stylesheet" href="/proyectofinaldesarrollo/css/ean-scanner.css?v=<?= substr(hash_file('sha256', 'public/css/ean-scanner.css'), 0, 12) ?>">
<div class="modal fade" id="escanerEan" tabindex="-1" aria-labelledby="tituloEscanerEan" aria-hidden="true" data-alternativa="/proyectofinaldesarrollo/lib/zxing/index.min.js">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="tituloEscanerEan">Escanear EAN</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancelar escaneo"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-3">
                <video playsinline muted autoplay hidden aria-label="Vista de la cámara para leer el código EAN"></video>
                <p class="alert alert-info mb-0" data-ean-estado role="status" aria-live="polite">Preparando cámara…</p>
                <p class="small text-secondary mb-0">Lee EAN-13 y EAN-8. Escanear no guarda cambios ni modifica el stock.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-ean-reintentar hidden>Reintentar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>
<!-- Cada pantalla decide qué hacer con el código; POS y Ventas no cargan estos scripts. -->
<script src="/proyectofinaldesarrollo/js/ean-scanner.js?v=<?= substr(hash_file('sha256', 'public/js/ean-scanner.js'), 0, 12) ?>" defer></script>
<script src="/proyectofinaldesarrollo/js/ean-modulos.js?v=<?= substr(hash_file('sha256', 'public/js/ean-modulos.js'), 0, 12) ?>" defer></script>
