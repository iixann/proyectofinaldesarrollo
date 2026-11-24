// Usa el dropdown de Bootstrap sin que la tabla responsive recorte sus opciones.
(() => {
    'use strict';
    if (!window.bootstrap) return;
    document.querySelectorAll('[data-producto-acciones]').forEach(boton => {
        bootstrap.Dropdown.getOrCreateInstance(boton, {
            boundary: 'viewport',
            // Posiciona respecto de la ventana y permite abrir hacia arriba en filas inferiores.
            popperConfig(configuracion) {
                return {
                    ...configuracion,
                    strategy: 'fixed',
                    modifiers: [
                        ...configuracion.modifiers,
                        {name: 'preventOverflow', options: {boundary: 'viewport', rootBoundary: 'viewport', altAxis: true, padding: 8}},
                        {name: 'flip', options: {boundary: 'viewport', rootBoundary: 'viewport', padding: 8}}
                    ]
                };
            }
        });
    });
})();
