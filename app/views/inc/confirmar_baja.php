<!-- Un solo modal confirma las bajas; cancelar no envía el formulario. -->
<div class="modal fade" id="confirmarBaja" tabindex="-1" aria-labelledby="tituloConfirmarBaja" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="tituloConfirmarBaja">Confirmar baja</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cancelar"></button></div>
        <div class="modal-body"><p data-pregunta-baja class="mb-0"></p></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" data-confirmar-baja-ahora>Confirmar</button></div>
    </div></div>
</div>
<script src="/proyectofinaldesarrollo/js/confirmar-baja.js?v=<?= substr(hash_file('sha256', 'public/js/confirmar-baja.js'), 0, 12) ?>" defer></script>
