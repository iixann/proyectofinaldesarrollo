// Conserva borradores por usuario, inicio de sesión y caja dentro de esta pestaña.
(() => {
    'use strict';
    const prefijo = 'pos-carrito:v1:';
    const contexto = document.getElementById('contextoCarrito');
    const identidad = contexto?.dataset.contexto + ':' + contexto?.dataset.usuario + ':';

    // Descarta borradores ajenos, de otra autenticación o de cajas ya cerradas.
    function depurar(sesiones = null) {
        try {
            const claves = Array.from({length: sessionStorage.length}, (_, i) => sessionStorage.key(i));
            claves.filter(clave => clave?.startsWith(prefijo)).forEach(clave => {
                if (!contexto?.dataset.usuario || !contexto.dataset.contexto || !clave.startsWith(prefijo + identidad)
                    || (sesiones && !sesiones.map(String).includes(clave.slice((prefijo + identidad).length)))) sessionStorage.removeItem(clave);
            });
        } catch (error) { /* El ingreso manual sigue disponible si el navegador bloquea storage. */ }
    }

    // Vacía todos los borradores al cerrar sesión en este navegador.
    function cerrarSesion() {
        try {
            for (let i = sessionStorage.length - 1; i >= 0; i--) {
                const clave = sessionStorage.key(i);
                if (clave?.startsWith(prefijo)) sessionStorage.removeItem(clave);
            }
        } catch (error) { /* El servidor siempre invalida la sesión aunque storage falle. */ }
    }

    // Crea un almacén que nunca comparte clave entre usuarios o cajas.
    function crear(usuario, caja, autenticacion) {
        const clave = prefijo + autenticacion + ':' + usuario + ':' + caja;
        // Solo almacena datos necesarios del carrito; nunca pagos ni credenciales.
        function guardar(lineas, seleccionada) {
            try {
                if (!lineas.length) sessionStorage.removeItem(clave);
                else sessionStorage.setItem(clave, JSON.stringify({version: 1, lineas, seleccionada}));
                return true;
            } catch (error) { return false; }
        }
        // Rechaza un borrador corrupto; el servidor vuelve a validar al confirmar.
        function leer() {
            try {
                const datos = JSON.parse(sessionStorage.getItem(clave));
                if (!datos || datos.version !== 1 || !Array.isArray(datos.lineas) || datos.lineas.length > 200) return null;
                return datos;
            } catch (error) { return null; }
        }
        // Abandonar o confirmar elimina únicamente el borrador de esta caja.
        function vaciar() { guardar([], null); }
        return {guardar, leer, vaciar};
    }
    // Al volver desde la caché de navegación, revalida la sesión en el servidor.
    window.addEventListener('pageshow', evento => { if (evento.persisted) window.location.reload(); });
    depurar();
    document.addEventListener('click', evento => {
        if (evento.target.closest('a[href$="/session/logout"]')) cerrarSesion();
    });
    window.CarritoTemporal = {crear, depurar, cerrarSesion};
})();
