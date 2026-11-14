// Coordina los renglones y las altas rápidas de compra o recepción.
(function () {
    'use strict';
    // Recibe los productos que el controlador dejó en la vista.
    const productos = JSON.parse(document.getElementById('datosProductosCompra').textContent);
    const formularioCompra = document.getElementById('formularioCompra');
    const esRecepcion = formularioCompra.dataset.contexto === 'recepcion';
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
    const botonProducto = formularioProducto.querySelector('button[type="submit"]');
    const permiteCrearProducto = !botonProducto.disabled;
    const comprobarEan = document.getElementById('consultarEanCompra');
    const panelExistente = document.getElementById('productoExistenteCompra');
    const datosNuevo = formularioProducto.querySelector('[data-datos-nuevo-producto]');
    let productoExistente = null;

    // Habilita crear o asociar según el EAN y la consulta pendiente.
    function actualizarAltaProducto() {
        // La recepción conserva su flujo compartido de alta de productos.
        if (esRecepcion) return;
        const asociable = productoExistente && productoExistente.activo && !productoExistente.asociado;
        botonProducto.textContent = productoExistente ? 'Asociar producto' : 'Crear producto';
        botonProducto.disabled = pendiente || (productoExistente ? !asociable : !permiteCrearProducto);
        comprobarEan.disabled = pendiente;
        formularioProducto.elements.codigo_barras.readOnly = pendiente;
    }
    // Vuelve al formulario de producto nuevo al cambiar el EAN.
    function limpiarProductoExistente() {
        // La recepción conserva su flujo compartido de alta de productos.
        if (esRecepcion) return;
        productoExistente = null;
        panelExistente.hidden = true;
        datosNuevo.hidden = false;
        datosNuevo.disabled = false;
        actualizarAltaProducto();
    }
    // Agrega la asociación disponible y actualiza los selectores.
    function incorporarProducto(producto) {
        if (!productos.some(item => String(item.id_producto) === String(producto.id_producto)
            && String(item.id_proveedor) === String(producto.id_proveedor))) productos.push(producto);
        cuerpo.querySelectorAll('select').forEach(actualizarProductos);
    }
    // Muestra si el producto puede asociarse al proveedor elegido.
    function mostrarProductoExistente(producto) {
        productoExistente = producto;
        datosNuevo.hidden = true;
        datosNuevo.disabled = true;
        panelExistente.hidden = false;
        panelExistente.querySelector('[data-existente-nombre]').textContent = producto.nombre_producto;
        panelExistente.querySelector('[data-existente-ean]').textContent = producto.codigo_barras;
        panelExistente.querySelector('[data-existente-mensaje]').textContent = !producto.activo
            ? 'El producto está inactivo. Debe reactivarse desde Productos antes de poder utilizarlo.'
            : producto.asociado
                ? 'Este producto ya está asociado al proveedor seleccionado. Seleccionalo desde la lista de productos.'
                : 'Todavía no está asociado al proveedor seleccionado. ¿Desea asociarlo? Se conservarán todos los datos y el stock del producto.';
        // Si ya está asociado y activo, lo ofrece en los renglones.
        if (producto.activo && producto.asociado) incorporarProducto(producto);
        actualizarAltaProducto();
    }
    // La consulta y asociación de un EAN existente corresponde a Compra.
    if (!esRecepcion) {
        formularioProducto.elements.codigo_barras.addEventListener('input', limpiarProductoExistente);
        // Consulta CompraController para saber si el EAN ya existe.
        function consultarEan() {
            if (pendiente || !formularioProducto.elements.codigo_barras.value.trim()) return;
            enviarAlta(formularioProducto, resultado => {
                if (resultado.existe) mostrarProductoExistente(resultado.producto);
                else limpiarProductoExistente();
            }, {action: '/proyectofinaldesarrollo/compra/consultarProductoRapido', cerrar: false});
        }
        comprobarEan.addEventListener('click', consultarEan);
        formularioProducto.elements.codigo_barras.addEventListener('change', consultarEan);
        formularioProducto.elements.codigo_barras.addEventListener('keydown', evento => {
            if (evento.key === 'Enter') { evento.preventDefault(); consultarEan(); }
        });
    }

    // Solo presentación: no agrega importes al POST ni interviene en el guardado.
    function mostrarTotalEstimado() {
        const salida = document.getElementById('totalCompra');
        if (!salida || esRecepcion) return;
        let total = 0;
        let completo = true;
        cuerpo.querySelectorAll('tr').forEach(function (fila) {
            const cantidad = fila.querySelector('input[name$="[cantidad]"]').value;
            const precio = fila.querySelector('input[name$="[precio_unitario]"]').value;
            if (cantidad === '' && precio === '') return;
            if (!/^\d+$/.test(cantidad) || !/^\d{1,10}(?:\.\d{1,2})?$/.test(precio)) {
                completo = false;
                return;
            }
            const partes = precio.split('.');
            const centavos = Number(partes[0]) * 100 + Number((partes[1] || '').padEnd(2, '0'));
            const subtotal = Number(cantidad) * centavos;
            if (!Number.isSafeInteger(subtotal) || !Number.isSafeInteger(total + subtotal)) {
                completo = false;
                return;
            }
            total += subtotal;
        });
        salida.textContent = completo ? new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(total / 100) : 'Revisá los importes';
    }
    cuerpo.addEventListener('input', mostrarTotalEstimado);

    // Muestra un mensaje de resultado o error en la pantalla.
    function informar(texto, error = false) {
        mensaje.textContent = texto;
        mensaje.className = 'alert ' + (error ? 'alert-warning' : 'alert-success');
    }

    // Carga los productos del proveedor y conserva la selección válida.
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

    // Habilita registrar cuando hay proveedor y productos disponibles.
    function actualizarDisponibilidad() {
        mostrarTotalEstimado();
        const disponibles = productos.some(producto => String(producto.id_proveedor) === proveedor.value);
        registrar.disabled = pendiente || !proveedor.value || !disponibles;
        const rechazar = document.getElementById('rechazarRecepcion');
        if (rechazar) rechazar.disabled = registrar.disabled;
        document.getElementById('avisoMaestros').classList.toggle('d-none', !!proveedor.value && disponibles);
        cuerpo.querySelectorAll('[data-nuevo-producto]').forEach(function (boton) {
            boton.disabled = pendiente || !proveedor.value;
        });
    }

    // Al cambiar proveedor, actualiza los productos de todos los renglones.
    proveedor.addEventListener('change', function () {
        cuerpo.querySelectorAll('select').forEach(actualizarProductos);
        actualizarDisponibilidad();
    });
    // Agrega un renglón vacío con nombres de campos independientes.
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
    // Atiende los botones para quitar filas o abrir el alta de producto.
    cuerpo.addEventListener('click', function (evento) {
        // Quita la fila elegida o vacía la última para conservar el formulario.
        if (evento.target.matches('[data-quitar]')) {
            const fila = evento.target.closest('tr');
            if (cuerpo.rows.length > 1) fila.remove();
            else fila.querySelectorAll('[name]').forEach(campo => { campo.value = ''; });
            mostrarTotalEstimado();
        }
        if (evento.target.matches('[data-nuevo-producto]') && proveedor.value && !pendiente) {
            filaProducto = evento.target.closest('tr');
            // La consulta y asociación de un EAN existente corresponde a Compra.
            if (!esRecepcion) {
                formularioProducto.reset();
                limpiarProductoExistente();
            }
            formularioProducto.elements.id_proveedor.value = proveedor.value;
            formularioProducto.querySelector('[data-error]').classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProductoRapido')).show();
        }
    });
    // Evita registrar mientras sigue pendiente un alta rápida.
    formularioCompra.addEventListener('submit', function (evento) {
        if (pendiente) {
            evento.preventDefault();
            informar(esRecepcion ? 'Esperá a que termine el alta rápida antes de registrar la recepción.' : 'Esperá a que termine el alta rápida antes de registrar la compra.', true);
        }
    });
    ['modalProveedorRapido', 'modalProductoRapido'].forEach(function (id) {
        // Mantiene abierto el modal mientras espera la respuesta del servidor.
        document.getElementById(id).addEventListener('hide.bs.modal', function (evento) {
            if (pendiente) evento.preventDefault();
        });
    });

    // Envía el formulario por POST y procesa el JSON del controlador.
    async function enviarAlta(formulario, alGuardar, opciones = {}) {
        if (pendiente) return;
        pendiente = true;
        const boton = formulario.querySelector('button[type="submit"]');
        const error = formulario.querySelector('[data-error]');
        error.classList.add('d-none');
        boton.disabled = true;
        proveedor.disabled = true;
        actualizarDisponibilidad();
        actualizarAltaProducto();
        let guardado = false;
        try {
            const datos = new FormData(formulario);
            datos.set('csrf_compra', formularioCompra.elements.csrf_compra.value);
            // La consulta escaneada usa el EAN y proveedor capturados, como strings.
            if (opciones.codigo !== undefined) datos.set('codigo_barras', opciones.codigo);
            if (opciones.proveedor !== undefined) datos.set('id_proveedor', opciones.proveedor);
            if (opciones.idProducto) datos.set('id_producto', String(opciones.idProducto));
            const respuesta = await fetch(opciones.action || formulario.action, { method: 'POST', body: datos, headers: { Accept: 'application/json' } });
            if (respuesta.redirected) throw new Error('La sesión venció. Conservá los datos y volvé a ingresar.');
            const resultado = await respuesta.json();
            // Si el alta detecta un EAN existente, ofrece asociarlo desde el modal.
            if (!respuesta.ok && resultado.existe && formulario === formularioProducto && !esRecepcion) {
                mostrarProductoExistente(resultado.producto);
                return;
            }
            if (!respuesta.ok || !resultado.ok) throw new Error(resultado.mensaje || 'No se pudo completar el alta.');
            alGuardar(resultado);
            guardado = opciones.cerrar !== false;
        } catch (excepcion) {
            error.textContent = excepcion instanceof SyntaxError ? 'La sesión puede haber vencido. Conservá los datos y volvé a ingresar.' : excepcion.message;
            error.classList.remove('d-none');
            // El escaneo consulta sin abrir el alta; muestra aquí también sus errores.
            if (opciones.codigo !== undefined) informar(error.textContent, true);
        } finally {
            pendiente = false;
            boton.disabled = false;
            proveedor.disabled = false;
            actualizarDisponibilidad();
            actualizarAltaProducto();
        }
        if (guardado) {
            bootstrap.Modal.getOrCreateInstance(formulario.closest('.modal')).hide();
            formulario.reset();
        }
    }

    // Crea el proveedor desde el modal y lo deja seleccionado.
    formularioProveedor.addEventListener('submit', function (evento) {
        evento.preventDefault();
        enviarAlta(formularioProveedor, function (resultado) {
            const nuevo = resultado.proveedor;
            proveedor.add(new Option(nuevo.razon_social, String(nuevo.id_proveedor)));
            proveedor.value = String(nuevo.id_proveedor);
            proveedor.dispatchEvent(new Event('change'));
            informar(esRecepcion ? 'Proveedor creado y seleccionado. Se conservaron los datos de la recepción; revisá los productos al cambiar de proveedor.' : 'Proveedor creado y seleccionado. Se conservaron comprobante, observación, cantidades y precios; revisá los productos al cambiar de proveedor.');
        });
    });
    // Crea o asocia el producto y lo selecciona en el renglón de origen.
    formularioProducto.addEventListener('submit', function (evento) {
        evento.preventDefault();
        const asociar = !esRecepcion && productoExistente !== null;
        // Impide asociar un producto inactivo o que ya pertenece al proveedor.
        if (asociar && (!productoExistente.activo || productoExistente.asociado)) return;
        const opciones = asociar ? {action: '/proyectofinaldesarrollo/compra/asociarProductoRapido', idProducto: productoExistente.id_producto} : {};
        enviarAlta(formularioProducto, function (resultado) {
            const nuevo = resultado.producto;
            incorporarProducto(nuevo);
            // Selecciona el producto en la fila que abrió el modal si sigue disponible.
            if (filaProducto && filaProducto.isConnected && String(nuevo.id_proveedor) === proveedor.value) {
                filaProducto.querySelector('select').value = String(nuevo.id_producto);
                if (!asociar) filaProducto.querySelectorAll('input').forEach(campo => { campo.value = ''; });
            }
            informar(asociar ? (resultado.ya_asociado ? 'El producto ya estaba asociado; quedó disponible y seleccionado en la compra.' : 'Producto existente asociado al proveedor y seleccionado. Completá cantidad y precio de compra; sus datos y stock no se modificaron.')
                : esRecepcion ? 'Producto creado y asociado al proveedor con stock 0. Completá la cantidad físicamente recibida.' : 'Producto creado y asociado al proveedor. Completá cantidad y precio de compra; su stock inicial es 0.');
            limpiarProductoExistente();
        }, opciones);
    });
    // Reutiliza una fila vacía o el botón actual para agregar un renglón.
    function filaParaEscaneo(idProducto = null) {
        const filas = Array.from(cuerpo.rows);
        const existente = idProducto === null ? null : filas.find(fila => fila.querySelector('select').value === String(idProducto));
        if (existente) return existente;
        const vacia = filas.find(fila => fila.querySelector('select').value === '');
        if (vacia) return vacia;
        document.getElementById('agregarRenglon').click();
        return cuerpo.rows[cuerpo.rows.length - 1];
    }

    // Selecciona el producto encontrado sin incrementar ni completar cantidades.
    function seleccionarEscaneado(producto) {
        incorporarProducto(producto);
        const fila = filaParaEscaneo(producto.id_producto);
        fila.querySelector('select').value = String(producto.id_producto);
        actualizarDisponibilidad();
        window.EanModulos.resaltarFila(fila);
        informar('Producto localizado. Completá la cantidad' + (esRecepcion ? ' físicamente recibida.' : ' y el precio de compra.'));
    }

    // Pasa el EAN al endpoint actual; solo la confirmación del modal puede asociar.
    function localizarEanCompra(evento) {
        const codigo = evento.detail?.codigo;
        if (pendiente) { informar('Esperá a que termine la operación pendiente.', true); return; }
        if (!proveedor.value || typeof codigo !== 'string' || codigo.trim() === '' || codigo.length > 50) {
            informar('Seleccioná un proveedor e ingresá un EAN válido.', true);
            return;
        }
        const idProveedor = proveedor.value;
        // Esta consulta no crea productos, asociaciones, compras ni movimientos.
        enviarAlta(formularioProducto, resultado => {
            const producto = resultado.producto;
            if (resultado.existe && producto.activo && producto.asociado) {
                seleccionarEscaneado(producto);
                return;
            }
            if (resultado.existe && !producto.activo) {
                informar('El producto está inactivo. Debe reactivarse desde Productos antes de utilizarlo.', true);
                return;
            }
            // Recepción Directa mantiene la administración de asociaciones en Productos.
            if (resultado.existe && esRecepcion) {
                informar('El producto no está asociado al proveedor. Asociálo desde Productos antes de recibirlo.', true);
                return;
            }
            filaProducto = filaParaEscaneo();
            formularioProducto.reset();
            limpiarProductoExistente();
            formularioProducto.elements.id_proveedor.value = idProveedor;
            formularioProducto.elements.codigo_barras.value = codigo.trim();
            formularioProducto.querySelector('[data-error]').classList.add('d-none');
            // El producto existente usa exactamente el panel y confirmación actuales.
            if (resultado.existe) mostrarProductoExistente(producto);
            informar(resultado.existe ? 'El producto existe. Confirmá su asociación al proveedor en el modal.' : 'El EAN no está registrado. Podés completar el alta rápida; no se creó ningún producto.');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProductoRapido')).show();
        }, {action: '/proyectofinaldesarrollo/compra/consultarProductoRapido', codigo: codigo.trim(), proveedor: idProveedor, cerrar: false});
    }

    // Las pantallas anteriores sin lector mantienen los mismos eventos.
    if (document.getElementById('escanearEanCompra')) {
        formularioCompra.addEventListener('ean:detectado', localizarEanCompra);
    }
    actualizarDisponibilidad();
}());
