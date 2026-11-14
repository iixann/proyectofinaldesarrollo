// Conecta el lector compartido con los formularios actuales de gestión.
(() => {
    'use strict';

    // Destaca la fila encontrada y permite escribir su cantidad sin cambiarla.
    function resaltarFila(fila) {
        document.querySelectorAll('.ean-localizado').forEach(item => item.classList.remove('ean-localizado'));
        fila.classList.add('ean-localizado');
        const cantidad = fila.querySelector('input[type="number"]');
        // En una tabla angosta, lleva también la cantidad al área visible.
        if (cantidad) {
            cantidad.focus({preventScroll: true});
            cantidad.scrollIntoView({block: 'center', inline: 'center', behavior: 'smooth'});
            cantidad.select();
        } else fila.scrollIntoView({block: 'center', inline: 'nearest', behavior: 'smooth'});
    }

    // Muestra el resultado sin insertar HTML procedente del EAN o del servidor.
    function informar(elemento, texto, error = false) {
        if (!elemento) return;
        elemento.textContent = texto;
        elemento.className = 'alert ' + (error ? 'alert-warning' : 'alert-success');
    }

    // Devuelve el código a cada pantalla sin guardar productos ni movimientos.
    function entregar(boton, codigo) {
        const campo = document.querySelector(boton.dataset.eanObjetivo);
        const modo = boton.dataset.eanModo;
        if (modo === 'recepcion') {
            const filas = Array.from(document.querySelectorAll('[data-ean-recepcion]'));
            const fila = filas.find(item => item.dataset.eanRecepcion === codigo);
            if (!fila) {
                informar(document.getElementById('mensajeEanRecepcion'), 'El producto escaneado no pertenece a esta recepción.', true);
                return;
            }
            informar(document.getElementById('mensajeEanRecepcion'), 'Producto localizado. Ingresá la cantidad físicamente recibida.');
            resaltarFila(fila);
            return;
        }
        if (!campo) return;
        campo.value = codigo;
        campo.dispatchEvent(new Event('input', {bubbles: true}));
        if (modo === 'compra') {
            // Compra conserva su consulta, su alta rápida y la confirmación de asociación.
            document.getElementById('formularioCompra').dispatchEvent(new CustomEvent('ean:detectado', {detail: {codigo}}));
        } else if (modo === 'filtro') {
            const formulario = campo.form;
            // La consulta exacta no debe quedar oculta por filtros anteriores.
            ['nombre', 'id_categoria', 'id_proveedor'].forEach(nombre => {
                if (formulario.elements[nombre]) formulario.elements[nombre].value = '';
            });
            formulario.elements.escaneado.value = '1';
            formulario.requestSubmit();
        } else {
            // En Productos solo completa el EAN; el usuario decide cuándo guardar.
            campo.focus();
            campo.select();
        }
    }

    // Impide consultar una compra sin proveedor o mientras hay un alta pendiente.
    function puedeEscanear(boton) {
        if (boton.dataset.eanModo !== 'compra') return true;
        const proveedor = document.getElementById('id_proveedor');
        if (proveedor.disabled) return false;
        if (!proveedor.value) {
            informar(document.getElementById('mensajeCompra'), 'Seleccioná un proveedor antes de escanear.', true);
            proveedor.focus();
            return false;
        }
        return true;
    }

    // Cada botón abre la misma cámara con un destino diferente.
    document.querySelectorAll('[data-escanear-ean]').forEach(boton => {
        boton.addEventListener('click', () => {
            if (!puedeEscanear(boton)) return;
            window.EanScanner.abrir(codigo => entregar(boton, codigo));
        });
    });
    const buscarCompra = document.querySelector('[data-buscar-ean-compra]');
    if (buscarCompra) {
        // También permite ingresar el código a mano cuando no se puede usar cámara.
        buscarCompra.addEventListener('click', () => {
            const boton = document.getElementById('escanearEanCompra');
            if (!puedeEscanear(boton)) return;
            entregar(boton, document.getElementById('eanCompra').value.trim());
        });
        document.getElementById('eanCompra').addEventListener('keydown', evento => {
            if (evento.key === 'Enter') { evento.preventDefault(); buscarCompra.click(); }
        });
    }
    // Recupera visualmente el resultado de las búsquedas GET de Stock y Reposición.
    const localizada = document.querySelector('[data-ean-localizado="1"]');
    if (localizada) {
        const resumen = document.querySelector('[data-ean-resumen]');
        // Stock prioriza la tarjeta legible en celular sobre la tabla horizontal.
        if (resumen) {
            localizada.classList.add('ean-localizado');
            resumen.scrollIntoView({block: 'center'});
        } else resaltarFila(localizada);
    }
    window.EanModulos = {resaltarFila};
})();
