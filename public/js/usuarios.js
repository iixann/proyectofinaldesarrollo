// La ayuda visual complementa las reglas que el modelo vuelve a validar.
(() => {
    'use strict';
    document.querySelectorAll('[data-roles-exclusivos]').forEach(grupo => {
        const campos = Array.from(grupo.querySelectorAll('input[name="roles[]"]'));
        const error = grupo.querySelector('[data-error-roles]');
        // Un rol de jerarquía desmarca y bloquea todos los demás.
        function actualizar(cambiado = null) {
            const esExclusivo = campo => ['ADMINISTRADOR', 'DUEÑO'].includes(campo.dataset.rol);
            const exclusivo = cambiado?.checked && esExclusivo(cambiado) ? cambiado : campos.find(c => c.checked && esExclusivo(c));
            campos.forEach(campo => {
                if (exclusivo && campo !== exclusivo) campo.checked = false;
                campo.disabled = !!exclusivo && campo !== exclusivo;
            });
            error.hidden = true;
        }
        campos.forEach(campo => campo.addEventListener('change', () => actualizar(campo)));
        // No deja enviar el formulario vacío; el backend también rechaza esa selección.
        grupo.closest('form').addEventListener('submit', evento => {
            if (!campos.some(campo => campo.checked)) {
                evento.preventDefault();
                error.hidden = false;
                campos[0]?.focus();
            }
        });
        actualizar();
    });
    if (!window.bootstrap) return;
    // Reutiliza el patrón Bootstrap/Popper de Productos sin alterar aquel módulo.
    document.querySelectorAll('[data-usuario-acciones]').forEach(boton => {
        bootstrap.Dropdown.getOrCreateInstance(boton, {
            boundary: 'viewport',
            // El menú puede salir del recorte de la tabla y abrir hacia arriba.
            popperConfig(configuracion) {
                return {...configuracion, strategy: 'fixed', modifiers: [
                    ...configuracion.modifiers,
                    {name: 'preventOverflow', options: {boundary: 'viewport', rootBoundary: 'viewport', altAxis: true, padding: 8}},
                    {name: 'flip', options: {boundary: 'viewport', rootBoundary: 'viewport', padding: 8}}
                ]};
            }
        });
    });
})();
