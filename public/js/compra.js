(function () {
    'use strict';
    const productos = JSON.parse(document.getElementById('datosProductosCompra').textContent);
    const formularioCompra = document.getElementById('formularioCompra');
    const proveedor = document.getElementById('id_proveedor');
    const cuerpo = document.getElementById('renglonesCompra');
    const plantilla = cuerpo.rows[0].cloneNode(true);
    const registrar = document.getElementById('registrarCompra');
    const formularioProveedor = document.getElementById('formularioProveedorRapido');
    const formularioProducto = document.getElementById('formularioProductoRapido');
    const mensaje = document.getElementById('mensajeCompra');
    let siguienteRenglon = cuerpo.rows.length;
    let pendiente = false;
    let filaProducto = null;

    function informar(texto, error = false) {
        mensaje.textContent = texto;
        mensaje.className = 'alert ' + (error ? 'alert-warning' : 'alert-success');
    }

    function actualizarProductos(select) {
        const anterior = select.value;
        select.replaceChildren(new Option('Seleccioná un producto', ''));
        productos.forEach(function (producto) {
            if (String(producto.id_proveedor) === proveedor.value) {
                select.add(new Option(producto.nombre_producto, String(producto.id_producto)));
            }
        });
        select.value = Array.from(select.options).some(opcion => opcion.value === anterior) ? anterior : '';
    }

    function actualizarDisponibilidad() {
        const disponibles = productos.some(producto => String(producto.id_proveedor) === proveedor.value);
        registrar.disabled = pendiente || !proveedor.value || !disponibles;
        document.getElementById('avisoMaestros').classList.toggle('d-none', !!proveedor.value && disponibles);
        cuerpo.querySelectorAll('[data-nuevo-producto]').forEach(function (boton) {
            boton.disabled = pendiente || !proveedor.value;
        });
    }

    proveedor.addEventListener('change', function () {
        cuerpo.querySelectorAll('select').forEach(actualizarProductos);
        actualizarDisponibilidad();
    });
    document.getElementById('agregarRenglon').addEventListener('click', function () {
        const fila = plantilla.cloneNode(true);
        fila.querySelectorAll('[name]').forEach(function (campo) {
            campo.name = campo.name.replace(/renglones\[\d+\]/, 'renglones[' + siguienteRenglon + ']');
            campo.value = '';
        });
        siguienteRenglon++;
        actualizarProductos(fila.querySelector('select'));
        cuerpo.appendChild(fila);
        actualizarDisponibilidad();
    });
    cuerpo.addEventListener('click', function (evento) {
        if (evento.target.matches('[data-quitar]')) {
            const fila = evento.target.closest('tr');
            if (cuerpo.rows.length > 1) fila.remove();
            else fila.querySelectorAll('[name]').forEach(campo => { campo.value = ''; });
        }
        if (evento.target.matches('[data-nuevo-producto]') && proveedor.value && !pendiente) {
            filaProducto = evento.target.closest('tr');
            formularioProducto.elements.id_proveedor.value = proveedor.value;
            formularioProducto.querySelector('[data-error]').classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProductoRapido')).show();
        }
    });
    formularioCompra.addEventListener('submit', function (evento) {
        if (pendiente) {
            evento.preventDefault();
            informar('Esperá a que termine el alta rápida antes de registrar la compra.', true);
        }
    });
    ['modalProveedorRapido', 'modalProductoRapido'].forEach(function (id) {
        document.getElementById(id).addEventListener('hide.bs.modal', function (evento) {
            if (pendiente) evento.preventDefault();
        });
    });

    async function enviarAlta(formulario, alGuardar) {
        if (pendiente) return;
        pendiente = true;
        const boton = formulario.querySelector('button[type="submit"]');
        const error = formulario.querySelector('[data-error]');
        error.classList.add('d-none');
        boton.disabled = true;
        proveedor.disabled = true;
        actualizarDisponibilidad();
        let guardado = false;
        try {
            const datos = new FormData(formulario);
            datos.set('csrf_compra', formularioCompra.elements.csrf_compra.value);
            const respuesta = await fetch(formulario.action, { method: 'POST', body: datos, headers: { Accept: 'application/json' } });
            const resultado = await respuesta.json();
            if (!respuesta.ok || !resultado.ok) throw new Error(resultado.mensaje || 'No se pudo completar el alta.');
            alGuardar(resultado);
            guardado = true;
        } catch (excepcion) {
            error.textContent = excepcion instanceof SyntaxError ? 'La sesión puede haber vencido. Conservá los datos y volvé a ingresar.' : excepcion.message;
            error.classList.remove('d-none');
        } finally {
            pendiente = false;
            boton.disabled = false;
            proveedor.disabled = false;
            actualizarDisponibilidad();
        }
        if (guardado) {
            bootstrap.Modal.getOrCreateInstance(formulario.closest('.modal')).hide();
            formulario.reset();
        }
    }

    formularioProveedor.addEventListener('submit', function (evento) {
        evento.preventDefault();
        enviarAlta(formularioProveedor, function (resultado) {
            const nuevo = resultado.proveedor;
            proveedor.add(new Option(nuevo.razon_social, String(nuevo.id_proveedor)));
            proveedor.value = String(nuevo.id_proveedor);
            proveedor.dispatchEvent(new Event('change'));
            informar('Proveedor creado y seleccionado. Se conservaron comprobante, observación, cantidades y precios; revisá los productos al cambiar de proveedor.');
        });
    });
    formularioProducto.addEventListener('submit', function (evento) {
        evento.preventDefault();
        enviarAlta(formularioProducto, function (resultado) {
            const nuevo = resultado.producto;
            productos.push(nuevo);
            cuerpo.querySelectorAll('select').forEach(actualizarProductos);
            if (filaProducto && filaProducto.isConnected && String(nuevo.id_proveedor) === proveedor.value) {
                filaProducto.querySelector('select').value = String(nuevo.id_producto);
                filaProducto.querySelectorAll('input').forEach(campo => { campo.value = ''; });
            }
            informar('Producto creado y asociado al proveedor. Completá cantidad y precio de compra; su stock inicial es 0.');
        });
    });
    actualizarDisponibilidad();
}());
