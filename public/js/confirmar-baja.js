// Intercepta solo los formularios de baja marcados en las vistas.
(() => {
    'use strict';
    const elemento = document.getElementById('confirmarBaja');
    if (!elemento || !window.bootstrap) return;
    const modal = bootstrap.Modal.getOrCreateInstance(elemento);
    const confirmar = elemento.querySelector('[data-confirmar-baja-ahora]');
    const autorizados = new WeakSet();
    let pendiente = null;
    let botonOrigen = null;
    document.addEventListener('submit', evento => {
        const formulario = evento.target;
        if (!formulario.matches('form[data-confirmar-baja]')) return;
        // Una confirmación permite exactamente un envío por el camino normal.
        if (autorizados.has(formulario)) { autorizados.delete(formulario); return; }
        evento.preventDefault();
        pendiente = formulario;
        botonOrigen = evento.submitter;
        confirmar.disabled = false;
        elemento.querySelector('[data-pregunta-baja]').textContent = formulario.dataset.confirmarBaja;
        modal.show();
    });
    // Conserva método POST, ruta y controles backend del formulario original.
    confirmar.addEventListener('click', () => {
        if (!pendiente || confirmar.disabled) return;
        confirmar.disabled = true;
        autorizados.add(pendiente);
        pendiente.requestSubmit(botonOrigen || undefined);
        modal.hide();
    });
    // Cancelar descarta la operación sin llamar al servidor.
    elemento.addEventListener('hidden.bs.modal', () => { pendiente = botonOrigen = null; });
})();
