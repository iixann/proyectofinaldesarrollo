// Conecta la consulta rápida y los atajos globales de navegación.
(() => {
    'use strict';
    const elemento = document.getElementById('consultaRapida');
    // Activa los atajos solo si la vista incluye el modal y Bootstrap.
    if (!elemento || !window.bootstrap) return;
    const modal = new bootstrap.Modal(elemento);
    const formulario = document.getElementById('formConsultaRapida');
    const ean = document.getElementById('eanConsultaRapida');
    const resultado = document.getElementById('resultadoConsultaRapida');
    const dinero = new Intl.NumberFormat('es-AR', {style: 'currency', currency: 'ARS'});
    let abierto = false;
    let focoAnterior;
    let consulta;
    let secuencia = 0;
    let reanudarCobro = false;

    // Abre la consulta rápida y conserva el foco para regresar.
    function abrir() {
        // Mientras la consulta está abierta, evita que los atajos actúen en el POS.
        if (abierto) { ean.focus(); ean.select(); return; }
        const cobro = document.getElementById('cobroPos');
        // Oculta el cobro antes de abrir la consulta y permite retomarlo después.
        if (cobro?.classList.contains('show')) {
            if (cobro.getAttribute('aria-busy') === 'true') return;
            reanudarCobro = true;
            cobro.addEventListener('hidden.bs.modal', abrir, {once: true});
            bootstrap.Modal.getInstance(cobro).hide();
            return;
        }
        // La consulta conserva los pagos y devuelve el foco al cobro al cerrar.
        if (document.querySelector('.modal.show')) return;
        focoAnterior = document.activeElement;
        abierto = true;
        resultado.replaceChildren();
        ean.value = '';
        modal.show();
    }
    elemento.addEventListener('shown.bs.modal', () => ean.focus());
    // Cancela la consulta pendiente e invalida su respuesta al cerrar.
    elemento.addEventListener('hide.bs.modal', () => {
        ++secuencia;
        consulta?.abort();
    });
    // Recupera el cobro o el campo de trabajo al terminar de cerrar.
    elemento.addEventListener('hidden.bs.modal', () => {
        abierto = false;
        // Al cerrar la consulta, vuelve al cobro que estaba abierto.
        if (reanudarCobro) {
            reanudarCobro = false;
            bootstrap.Modal.getInstance(document.getElementById('cobroPos')).show();
            return;
        }
        const destino = document.querySelector('#terminalPos #codigo') || focoAnterior;
        if (destino?.isConnected && !destino.disabled) destino.focus();
    });
    document.getElementById('abrirConsultaRapida').addEventListener('click', abrir);
    // Captura previa al listener del POS: ningún atajo interno opera sobre
    // el carrito o los pagos mientras la consulta está abierta.
    document.addEventListener('keydown', evento => {
        const alt = evento.altKey && !evento.ctrlKey && !evento.metaKey && !evento.shiftKey;
        const tecla = evento.key.toLowerCase();
        const f9 = evento.key === 'F9' && !evento.altKey && !evento.ctrlKey
            && !evento.metaKey && !evento.shiftKey && document.querySelector('.pos-pantalla');
        // Alt+Q o F9 en el POS abre la consulta rápida.
        if ((alt && tecla === 'q') || f9) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            if (!evento.repeat) abrir();
            return;
        }
        // Mientras la consulta está abierta, evita que los atajos actúen en el POS.
        if (abierto) {
            evento.stopPropagation();
            if (evento.key === 'Escape') { evento.preventDefault(); modal.hide(); }
            else if (/^F\d+$/.test(evento.key)) evento.preventDefault();
            return;
        }
        if (!alt) return;
        const enlace = Array.from(document.querySelectorAll('#atajosGlobales [data-atajo]'))
            .find(item => item.dataset.atajo === tecla);
        if (enlace) {
            evento.preventDefault();
            if (!evento.repeat) window.location.assign(enlace.href);
        }
    }, true);

    // Consulta el EAN en PosController y muestra el resultado en el modal.
    formulario.addEventListener('submit', async evento => {
        evento.preventDefault();
        const codigo = ean.value.trim();
        if (!codigo) { ean.focus(); return; }
        consulta?.abort();
        consulta = new AbortController();
        const actual = ++secuencia;
        resultado.textContent = 'Consultando…';
        ean.value = '';
        ean.focus();
        try {
            // Pasa el EAN por GET al controlador de consulta de productos.
            const respuesta = await fetch(formulario.action + '?' + new URLSearchParams({ean: codigo}), {
                headers: {Accept: 'application/json'}, signal: consulta.signal
            });
            if (respuesta.redirected) throw new Error('Iniciá sesión nuevamente para consultar.');
            if (!(respuesta.headers.get('content-type') || '').includes('application/json')) {
                throw new Error(respuesta.status === 403 ? 'Acceso denegado.' : 'No se pudo consultar el producto.');
            }
            const datos = await respuesta.json();
            // Ignora respuestas viejas o recibidas después de cerrar el modal.
            if (actual !== secuencia || !abierto) return;
            if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'No se pudo consultar el producto.');
            const producto = datos.producto;
            const lista = document.createElement('dl');
            lista.className = 'mb-0';
            const campos = [
                ['Producto', producto.nombre_producto], ['EAN', producto.codigo_barras],
                ['Precio', dinero.format(Number(producto.precio_venta))], ['Categoría', producto.categoria],
                ['Stock', producto.stock_actual + (producto.stock_actual <= 0 ? ' · SIN STOCK' : '')],
                ['Proveedores', producto.proveedores.length ? producto.proveedores.join(' · ') : 'Sin proveedores asociados']
            ];
            campos.forEach(([etiqueta, valor]) => {
                const titulo = document.createElement('dt');
                const detalle = document.createElement('dd');
                titulo.className = 'small text-secondary';
                titulo.textContent = etiqueta;
                detalle.textContent = valor;
                lista.append(titulo, detalle);
            });
            resultado.replaceChildren(lista);
        } catch (error) {
            if (actual === secuencia && abierto && error.name !== 'AbortError') resultado.textContent = error.message;
        } finally {
            if (actual === secuencia && abierto) ean.focus();
        }
    });
})();
