'use strict';

function precioCentavosPos(precio) {
    const partes = /^(\d{1,10})(?:\.(\d{1,2}))?$/.exec(String(precio));
    if (!partes) throw new Error('El precio del producto no es válido.');
    return Number(partes[1]) * 100 + Number((partes[2] || '').padEnd(2, '0'));
}

// Carrito temporal: solo la confirmación del servidor registra la venta y cambia stock.
function crearCarritoPos() {
    const productos = new Map();
    function total() {
        return Array.from(productos.values()).reduce((suma, linea) => suma + linea.subtotal, 0);
    }
    function guardar(linea) {
        if (!Number.isInteger(linea.cantidad) || linea.cantidad < 1) throw new Error('La cantidad mínima es 1.');
        if (linea.cantidad > linea.stock_disponible) throw new Error('Stock disponible: ' + linea.stock_disponible + '. No se puede agregar más.');
        linea.subtotal = linea.cantidad * linea.precio_centavos;
        const anterior = productos.get(String(linea.id_producto));
        const nuevoTotal = total() - (anterior ? anterior.subtotal : 0) + linea.subtotal;
        if (!Number.isSafeInteger(linea.subtotal) || !Number.isSafeInteger(nuevoTotal)) throw new Error('El importe supera el máximo que puede calcular esta pantalla.');
        productos.set(String(linea.id_producto), linea);
    }
    return {
        agregar(producto) {
            const stock = Number(producto.stock_actual);
            if (!Number.isInteger(stock) || stock <= 0) throw new Error('SIN STOCK: no se puede agregar este producto.');
            const actual = productos.get(String(producto.id_producto));
            guardar({
                id_producto: producto.id_producto,
                codigo_barras: String(producto.codigo_barras),
                nombre_producto: producto.nombre_producto,
                precio_unitario: String(producto.precio_venta),
                precio_centavos: precioCentavosPos(producto.precio_venta),
                stock_disponible: stock,
                cantidad: actual ? actual.cantidad + 1 : 1
            });
        },
        cambiarCantidad(id, cantidad) {
            const linea = productos.get(String(id));
            if (!linea) return;
            guardar({...linea, cantidad: Number(cantidad)});
        },
        eliminar(id) { productos.delete(String(id)); },
        vaciar() { productos.clear(); },
        lineas() { return Array.from(productos.values(), linea => ({...linea})); },
        totalCentavos: total
    };
}

function formatoDineroPos(centavos) {
    const enteros = String(Math.floor(centavos / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return '$ ' + enteros + ',' + String(centavos % 100).padStart(2, '0');
}

function decimalPos(centavos) {
    return String(Math.floor(centavos / 100)) + '.' + String(centavos % 100).padStart(2, '0');
}

function resumirPagosPos(entradas, total) {
    // El efectivo se deriva de la entrega, descontando primero los otros medios.
    const otrosAplicados = entradas.filter(entrada => !entrada.efectivo).reduce((suma, entrada) => {
        const nuevo = suma + precioCentavosPos(entrada.monto.trim() || '0');
        if (!Number.isSafeInteger(nuevo) || nuevo > 999999999999) throw new Error('Los pagos superan el máximo permitido.');
        return nuevo;
    }, 0);
    const saldoEfectivo = Math.max(total - otrosAplicados, 0);
    let aplicado = 0;
    const pagos = [];
    const usados = new Set();
    for (const entrada of entradas) {
        let entregado = 0;
        if (entrada.efectivo) {
            try { entregado = precioCentavosPos(entrada.entregado.trim() || '0'); }
            catch (error) { throw new Error('Ingresá una entrega válida, con hasta dos decimales.'); }
        }
        const monto = entrada.efectivo ? Math.min(entregado, saldoEfectivo)
            : precioCentavosPos(entrada.monto.trim() || '0');
        if (monto === 0) continue;
        if (usados.has(entrada.id_medio_pago)) throw new Error('No repitas un medio de pago.');
        usados.add(entrada.id_medio_pago);
        aplicado += monto;
        if (!Number.isSafeInteger(aplicado) || aplicado > 999999999999) throw new Error('Los pagos superan el máximo permitido.');
        const pago = {id_medio_pago: entrada.id_medio_pago, monto: decimalPos(monto), referencia: entrada.referencia || ''};
        if (entrada.efectivo) {
            pago.monto_entregado = decimalPos(entregado);
            pago.vuelto = decimalPos(entregado - monto);
        }
        pagos.push(pago);
    }
    return {pagos, aplicado, diferencia: total - aplicado};
}

function iniciarPos() {
    const terminal = document.getElementById('terminalPos');
    if (!terminal) return;
    const carrito = crearCarritoPos();
    const selector = document.getElementById('sesionPos');
    const botonSesion = document.getElementById('elegirSesion');
    const mensaje = document.getElementById('mensajePos');
    const codigo = document.getElementById('codigo');
    const cuerpo = document.getElementById('lineasCarrito');
    const resultados = document.getElementById('productosEncontrados');
    const seccionResultados = document.getElementById('resultadosBusqueda');
    const sesion = terminal.dataset.sesion;
    let pendientes = 0;
    let ultimaBusquedaNombre = 0;
    let sesionDisponible = true;
    let confirmando = false;
    let ultimaVenta = null;
    const ticketBoton = document.getElementById('verUltimaVenta');
    function abrirTicketUltimaVenta() {
        if (ultimaVenta === null) {
            informar('No hay una venta reciente para imprimir.');
            return;
        }
        window.open(ticketBoton.href, '_blank', 'noopener');
    }
    ticketBoton.addEventListener('click', evento => {
        evento.preventDefault();
        abrirTicketUltimaVenta();
    });
    let seleccionada = null;
    let ultimaAgregada = null;
    let temporizadorResaltado;
    const confirmarBoton = document.getElementById('confirmarVenta');
    const abrirCobroBoton = document.getElementById('abrirCobro');
    const cobroElemento = document.getElementById('cobroPos');
    const cobroModal = new bootstrap.Modal(cobroElemento, {backdrop: 'static', keyboard: false});
    let cobroAbierto = false;
    let totalConPagos = null;
    let avisoPagos = false;
    function enfocarMedio() {
        const entrada = medioSeleccionado?.querySelector('[data-entregado]') || medioSeleccionado?.querySelector('[data-monto]');
        entrada?.focus();
        entrada?.select();
    }
    function abrirCobro(fila = null) {
        if (confirmando || pendientes > 0 || !sesionDisponible || !carrito.lineas().length) return;
        document.getElementById('mensajeCobro').hidden = true;
        elegirMedio(fila || medioSeleccionado || filasPago[0], false);
        cobroModal.show();
        enfocarMedio();
    }
    function volverVenta() {
        if (!confirmando) cobroModal.hide();
    }
    cobroElemento.addEventListener('shown.bs.modal', () => {
        cobroAbierto = true;
        enfocarMedio();
    });
    cobroElemento.addEventListener('hide.bs.modal', evento => {
        if (confirmando) evento.preventDefault();
    });
    cobroElemento.addEventListener('hidden.bs.modal', () => {
        cobroAbierto = false;
        if (sesionDisponible) codigo.focus();
    });
    abrirCobroBoton.addEventListener('click', () => abrirCobro());
    document.getElementById('volverVenta').addEventListener('click', volverVenta);
    const filasPago = Array.from(document.querySelectorAll('#filasPago [data-medio]'));
    const estadoPagos = document.getElementById('estadoPagos');
    const botonesMedio = Array.from(document.querySelectorAll('[data-elegir-medio]'));
    let medioSeleccionado = null;
    function elegirMedio(fila, enfocar = true, proponer = true) {
        if (!fila || confirmando || !sesionDisponible) return;
        if (proponer && fila.dataset.efectivo !== '1') {
            const entrada = fila.querySelector('[data-monto]');
            // Una selección nueva completa el saldo; editar un pago conserva su importe.
            if (entrada.value === '' || entrada.value === '0' || entrada.value === '0.00') {
                try {
                    const aplicado = filasPago.reduce((suma, medio) => suma + precioCentavosPos(medio.querySelector('[data-monto]').value || '0'), 0);
                    const falta = Math.max(0, carrito.totalCentavos() - aplicado);
                    if (falta > 0) entrada.value = decimalPos(falta);
                } catch (error) { /* No ocultar un importe inválido ya escrito. */ }
            }
            totalConPagos = carrito.totalCentavos();
        }
        medioSeleccionado = fila;
        filasPago.forEach(medio => { medio.hidden = medio !== fila; });
        botonesMedio.forEach(boton => {
            const activo = boton.dataset.elegirMedio === fila.dataset.medio;
            boton.classList.toggle('active', activo);
            boton.setAttribute('aria-expanded', activo ? 'true' : 'false');
        });
        actualizarCobro();
        if (enfocar) enfocarMedio();
    }
    botonesMedio.forEach(boton => boton.addEventListener('click', () => {
        elegirMedio(filasPago.find(fila => fila.dataset.medio === boton.dataset.elegirMedio));
    }));
    medioSeleccionado = filasPago.find(fila => fila.dataset.efectivo === '1') || filasPago[0];
    function dibujarResumenPagos() {
        const lista = document.getElementById('resumenPagos');
        lista.replaceChildren();
        filasPago.forEach(fila => {
            try {
                const monto = precioCentavosPos(fila.querySelector('[data-monto]').value || '0');
                if (monto <= 0) return;
                const item = document.createElement('li');
                const editar = boton(fila.dataset.nombre, 'Editar pago ' + fila.dataset.nombre,
                    () => elegirMedio(fila), 'btn-link');
                editar.disabled = confirmando || !sesionDisponible;
                const importe = document.createElement('strong');
                importe.textContent = formatoDineroPos(monto);
                item.appendChild(editar);
                item.appendChild(importe);
                const quitar = boton('×', 'Quitar pago ' + fila.dataset.nombre, () => {
                    fila.querySelectorAll('input').forEach(input => { input.value = ''; });
                    actualizarCobro();
                    enfocarMedio();
                }, 'btn-link text-danger');
                quitar.disabled = confirmando || !sesionDisponible;
                item.appendChild(quitar);
                lista.appendChild(item);
            } catch (error) { /* El validador de cobro muestra el importe inválido. */ }
        });
        lista.hidden = lista.children.length === 0;
    }

    function informar(texto, error = false) {
        mensaje.textContent = texto;
        mensaje.className = 'alert ' + (error ? 'alert-warning' : 'alert-info');
        if (error && cobroAbierto) {
            const aviso = document.getElementById('mensajeCobro');
            aviso.textContent = texto;
            aviso.hidden = false;
        }
    }
    function bloquearSelector() {
        const bloqueado = confirmando || pendientes > 0 || carrito.lineas().length > 0;
        selector.disabled = bloqueado;
        botonSesion.disabled = bloqueado;
        actualizarCobro();
        document.getElementById('avisoSesion').textContent = bloqueado ? 'Vaciá el carrito y esperá las consultas pendientes para cambiar de caja.' : 'Podés cambiar de caja mientras el carrito esté vacío.';
    }
    function celda(fila, valor) {
        const td = document.createElement('td');
        td.textContent = valor;
        fila.appendChild(td);
        return td;
    }
    function boton(texto, etiqueta, accion, estilo = 'btn-outline-secondary') {
        const elemento = document.createElement('button');
        elemento.type = 'button';
        elemento.className = 'btn btn-sm ' + estilo;
        elemento.textContent = texto;
        elemento.setAttribute('aria-label', etiqueta);
        elemento.addEventListener('click', accion);
        return elemento;
    }
    function cambiar(id, cantidad) {
        try {
            if (confirmando) throw new Error('Esperá la confirmación de la venta.');
            if (!sesionDisponible) throw new Error('La sesión ya no está disponible. Vaciá el carrito y elegí otra sesión.');
            carrito.cambiarCantidad(id, cantidad);
        } catch (error) { informar(error.message, true); }
        dibujarCarrito();
    }
    function seleccionarLinea(id) {
        seleccionada = String(id);
        Array.from(cuerpo.children).forEach(fila => {
            fila.classList.toggle('pos-seleccionada', fila.dataset.producto === seleccionada);
        });
    }
    function quitarLinea(id) {
        if (confirmando) return;
        carrito.eliminar(id);
        if (String(id) === seleccionada) seleccionada = null;
        dibujarCarrito();
        if (sesionDisponible) codigo.focus();
    }
    function dibujarCarrito() {
        if (totalConPagos !== null && totalConPagos !== carrito.totalCentavos()) {
            limpiarPagos();
            avisoPagos = carrito.lineas().length > 0;
        }
        cuerpo.replaceChildren();
        const lineas = carrito.lineas();
        document.getElementById('carritoVacio').hidden = lineas.length > 0;
        document.getElementById('tablaCarrito').hidden = !lineas.length;
        lineas.forEach(function (linea) {
            const fila = document.createElement('tr');
            fila.dataset.producto = String(linea.id_producto);
            fila.classList.toggle('pos-seleccionada', fila.dataset.producto === seleccionada);
            fila.classList.toggle('pos-reciente', fila.dataset.producto === ultimaAgregada);
            fila.addEventListener('click', () => seleccionarLinea(linea.id_producto));
            const producto = celda(fila, linea.nombre_producto);
            const ean = document.createElement('small');
            ean.className = 'd-block text-secondary';
            ean.textContent = 'EAN: ' + linea.codigo_barras;
            producto.appendChild(ean);
            const celdaCantidad = celda(fila, '');
            const controles = document.createElement('div');
            controles.className = 'pos-cantidad';
            celdaCantidad.appendChild(controles);
            controles.appendChild(boton('−', 'Disminuir ' + linea.nombre_producto, () => cambiar(linea.id_producto, Math.max(1, linea.cantidad - 1))));
            const cantidad = document.createElement('input');
            cantidad.type = 'number';
            cantidad.min = '1';
            cantidad.max = String(linea.stock_disponible);
            cantidad.step = '1';
            cantidad.value = String(linea.cantidad);
            cantidad.className = 'form-control form-control-sm';
            cantidad.style.width = '5rem';
            cantidad.setAttribute('aria-label', 'Cantidad de ' + linea.nombre_producto);
            cantidad.addEventListener('change', () => cambiar(linea.id_producto, cantidad.value));
            controles.appendChild(cantidad);
            controles.appendChild(boton('+', 'Aumentar ' + linea.nombre_producto, () => cambiar(linea.id_producto, linea.cantidad + 1)));
            celda(fila, formatoDineroPos(linea.precio_centavos));
            celda(fila, formatoDineroPos(linea.subtotal));
            celda(fila, '').appendChild(boton('×', 'Eliminar ' + linea.nombre_producto, function () {
                quitarLinea(linea.id_producto);
            }, 'btn-outline-danger'));
            cuerpo.appendChild(fila);
        });
        document.getElementById('totalCarrito').textContent = formatoDineroPos(carrito.totalCentavos());
        const unidades = lineas.reduce((suma, linea) => suma + linea.cantidad, 0);
        const articulos = unidades + (unidades === 1 ? ' artículo' : ' artículos');
        document.getElementById('cantidadArticulos').textContent = articulos;
        document.getElementById('articulosCobro').textContent = articulos;
        document.getElementById('totalCobro').textContent = formatoDineroPos(carrito.totalCentavos());
        bloquearSelector();
    }
    function agregar(producto, codigoConsultado = null) {
        try {
            if (confirmando) throw new Error('Esperá la confirmación de la venta.');
            if (!sesionDisponible) throw new Error('La sesión ya no está disponible. Vaciá el carrito y elegí otra sesión.');
            carrito.agregar(producto);
            seleccionada = String(producto.id_producto);
            ultimaAgregada = seleccionada;
            clearTimeout(temporizadorResaltado);
            temporizadorResaltado = setTimeout(() => {
                ultimaAgregada = null;
                Array.from(cuerpo.children).forEach(fila => fila.classList.remove('pos-reciente'));
            }, 1600);
            dibujarCarrito();
            informar(producto.nombre_producto + ' agregado al carrito.');
            if (codigoConsultado === null || codigo.value.trim() === codigoConsultado) codigo.value = '';
            codigo.focus();
        } catch (error) { informar(error.message, true); }
    }
    function mostrarResultados(productos) {
        resultados.replaceChildren();
        seccionResultados.hidden = !productos.length;
        productos.forEach(function (producto) {
            const fila = document.createElement('tr');
            [producto.nombre_producto, producto.categoria, producto.codigo_barras, formatoDineroPos(precioCentavosPos(producto.precio_venta)), producto.stock_actual].forEach(valor => celda(fila, valor));
            const accion = celda(fila, '');
            if (producto.stock_actual <= 0) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger-subtle text-danger-emphasis';
                badge.textContent = 'SIN STOCK';
                accion.appendChild(badge);
            } else {
                const agregarBoton = boton('Agregar', 'Agregar ' + producto.nombre_producto, () => agregar(producto), 'btn-primary');
                agregarBoton.disabled = !sesionDisponible;
                accion.appendChild(agregarBoton);
            }
            resultados.appendChild(fila);
        });
    }
    async function buscar(formulario, porCodigo) {
        if (confirmando || !sesionDisponible) return;
        const busquedaNombre = porCodigo ? null : ++ultimaBusquedaNombre;
        const parametros = new URLSearchParams(new FormData(formulario));
        parametros.set('sesion', sesion);
        pendientes++;
        bloquearSelector();
        informar('Buscando…');
        try {
            const respuesta = await fetch(formulario.action + '?' + parametros.toString(), {headers: {'Accept': 'application/json'}});
            if (respuesta.redirected) throw new Error('Iniciá sesión nuevamente para operar.');
            if (!(respuesta.headers.get('content-type') || '').includes('application/json')) throw new Error(respuesta.status === 403 ? 'Acceso denegado.' : 'No se pudo consultar productos.');
            const datos = await respuesta.json();
            if (datos.sesion_no_disponible) {
                sesionDisponible = false;
                terminal.querySelectorAll('form input, form button').forEach(elemento => { elemento.disabled = true; });
                resultados.replaceChildren();
                seccionResultados.hidden = true;
            }
            if (!datos.ok) throw new Error(datos.mensaje);
            if (!sesionDisponible) return;
            if (porCodigo) {
                agregar(datos.producto, (parametros.get('codigo') || '').trim());
            } else if (busquedaNombre === ultimaBusquedaNombre) {
                mostrarResultados(datos.productos);
                informar(datos.productos.length ? 'Elegí un producto para agregar. Hasta 30 resultados.' : 'No se encontraron productos.');
            }
        } catch (error) {
            informar(error.message || 'No se pudo consultar productos.', true);
        } finally {
            pendientes--;
            bloquearSelector();
        }
    }
    function entradasPago() {
        return filasPago.map(fila => ({
            id_medio_pago: Number(fila.dataset.medio),
            efectivo: fila.dataset.efectivo === '1',
            monto: fila.querySelector('[data-monto]').value,
            entregado: fila.querySelector('[data-entregado]')?.value || '',
            referencia: fila.querySelector('[data-referencia]')?.value || ''
        }));
    }
    function actualizarCobro() {
        abrirCobroBoton.disabled = confirmando || pendientes > 0 || !sesionDisponible || !carrito.lineas().length;
        confirmarBoton.disabled = true;
        document.getElementById('avisoCambioPagos').hidden = !avisoPagos;
        const faltaElemento = document.getElementById('faltaPagar');
        const efectivo = filasPago.find(fila => fila.dataset.efectivo === '1');
        try {
            const resumen = resumirPagosPos(entradasPago(), carrito.totalCentavos());
            if (efectivo) {
                const pago = resumen.pagos.find(pago => String(pago.id_medio_pago) === efectivo.dataset.medio);
                efectivo.querySelector('[data-monto]').value = pago?.monto || '0.00';
                const entregado = precioCentavosPos(efectivo.querySelector('[data-entregado]').value.trim() || '0');
                const vuelto = entregado - precioCentavosPos(pago?.monto || '0');
                const vueltoElemento = efectivo.querySelector('[data-vuelto]');
                vueltoElemento.textContent = 'Vuelto: ' + formatoDineroPos(vuelto);
                vueltoElemento.classList.toggle('pos-vuelto-destacado', vuelto > 0);
            }
            document.getElementById('totalPagado').textContent = formatoDineroPos(resumen.aplicado);
            faltaElemento.textContent = resumen.diferencia >= 0 ? formatoDineroPos(resumen.diferencia) : 'Exceso: ' + formatoDineroPos(-resumen.diferencia);
            faltaElemento.classList.toggle('pos-falta-destacada', resumen.diferencia > 0);
            if (!carrito.lineas().length) estadoPagos.textContent = 'Agregá productos para iniciar el cobro.';
            else if (resumen.diferencia > 0) estadoPagos.textContent = 'Falta pagar: ' + formatoDineroPos(resumen.diferencia);
            else if (resumen.diferencia < 0) estadoPagos.textContent = 'Los pagos exceden el total en ' + formatoDineroPos(-resumen.diferencia) + '. Revisá los importes.';
            else estadoPagos.textContent = 'El total está cubierto.';
            confirmarBoton.disabled = confirmando || pendientes > 0 || !sesionDisponible || !carrito.lineas().length || !resumen.pagos.length || resumen.diferencia !== 0;
        } catch (error) {
            if (efectivo) {
                efectivo.querySelector('[data-monto]').value = '0.00';
                efectivo.querySelector('[data-vuelto]').textContent = 'Vuelto: —';
                efectivo.querySelector('[data-vuelto]').classList.remove('pos-vuelto-destacado');
            }
            document.getElementById('totalPagado').textContent = 'Revisar importes';
            faltaElemento.textContent = 'Revisar importes';
            faltaElemento.classList.remove('pos-falta-destacada');
            estadoPagos.textContent = error.message;
        }
        dibujarResumenPagos();
    }
    function limpiarPagos() {
        filasPago.forEach(fila => fila.querySelectorAll('input').forEach(input => { input.value = ''; }));
        totalConPagos = null;
        avisoPagos = false;
    }
    filasPago.forEach(fila => fila.addEventListener('input', () => {
        totalConPagos = carrito.totalCentavos();
        avisoPagos = false;
        actualizarCobro();
    }));
    confirmarBoton.addEventListener('click', async function () {
        if (!cobroAbierto || confirmando || pendientes > 0 || !sesionDisponible || !carrito.lineas().length) return;
        let resumen;
        try {
            resumen = resumirPagosPos(entradasPago(), carrito.totalCentavos());
            if (!resumen.pagos.length || resumen.diferencia !== 0) throw new Error('Los pagos deben cubrir exactamente el total.');
        } catch (error) { informar(error.message, true); return; }
        confirmando = true;
        cobroElemento.setAttribute('aria-busy', 'true');
        bloquearSelector();
        terminal.querySelectorAll('input, button').forEach(elemento => { elemento.disabled = true; });
        confirmarBoton.textContent = 'Registrando venta…';
        try {
            const respuesta = await fetch('/proyectofinaldesarrollo/venta/confirmar', {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': terminal.dataset.token},
                body: JSON.stringify({
                    id_caja_sesion: Number(sesion),
                    productos: carrito.lineas().map(linea => ({id_producto: linea.id_producto, cantidad: linea.cantidad})),
                    pagos: resumen.pagos
                })
            });
            if (respuesta.redirected) throw new Error('Iniciá sesión nuevamente para confirmar.');
            if (!(respuesta.headers.get('content-type') || '').includes('application/json')) throw new Error('No se recibió confirmación de la venta. No se reenvió automáticamente.');
            const datos = await respuesta.json();
            if (datos.sesion_no_disponible) sesionDisponible = false;
            if (!datos.ok) throw new Error(datos.mensaje || 'No se pudo registrar la venta.');
            carrito.vaciar();
            limpiarPagos();
            resultados.replaceChildren();
            seccionResultados.hidden = true;
            codigo.value = '';
            ultimaVenta = datos.id_venta;
            ticketBoton.href = '/proyectofinaldesarrollo/venta/ticket/' + encodeURIComponent(String(ultimaVenta)) + '?imprimir=1';
            ticketBoton.hidden = false;
            informar('Venta #' + datos.id_venta + ' registrada correctamente. Total: $ ' + datos.total);
        } catch (error) {
            informar(error.message || 'No se recibió confirmación de la venta. No se reenvió automáticamente.', true);
        } finally {
            confirmando = false;
            terminal.querySelectorAll('input, button').forEach(elemento => { elemento.disabled = !sesionDisponible; });
            document.getElementById('vaciarCarrito').disabled = false;
            cobroElemento.removeAttribute('aria-busy');
            confirmarBoton.textContent = 'CONFIRMAR VENTA · F8';
            dibujarCarrito();
            if (!carrito.lineas().length || !sesionDisponible) volverVenta();
            else enfocarMedio();
        }
    });
    document.getElementById('buscarEan').addEventListener('submit', function (evento) {
        evento.preventDefault();
        buscar(evento.currentTarget, true);
    });
    document.getElementById('buscarNombre').addEventListener('submit', function (evento) {
        evento.preventDefault();
        buscar(evento.currentTarget, false);
    });
    document.getElementById('vaciarCarrito').addEventListener('click', function () {
        if (confirmando || pendientes > 0) return;
        carrito.vaciar();
        limpiarPagos();
        dibujarCarrito();
        informar(sesionDisponible ? 'Carrito vacío.' : 'Carrito vacío. Elegí una sesión abierta o volvé a Sesiones de caja.');
        if (sesionDisponible) codigo.focus();
    });
    // Los atajos no eliminan líneas mientras se escribe en un campo.
    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'F10' && !evento.ctrlKey && !evento.altKey && !evento.metaKey) {
            evento.preventDefault();
            if (!evento.repeat && !confirmando) abrirTicketUltimaVenta();
            return;
        }
        if (confirmando || !sesionDisponible || evento.ctrlKey || evento.altKey || evento.metaKey) return;
        const escribiendo = evento.target.closest('input, textarea, select, [contenteditable="true"]');
        if (evento.repeat && /^F[4-8]$/.test(evento.key)) { evento.preventDefault(); return; }
        if (evento.key === 'F2') {
            evento.preventDefault();
            if (cobroAbierto) volverVenta();
            codigo.focus();
        } else if (evento.key === 'F3') {
            evento.preventDefault();
            if (cobroAbierto) volverVenta();
            document.getElementById('nombre').focus();
        } else if (['F4', 'F5', 'F6', 'F7'].includes(evento.key)) {
            evento.preventDefault();
            const nombres = {F4: ['EFECTIVO'], F5: ['DÉBITO'], F6: ['CRÉDITO'], F7: ['TRANSFERENCIA', 'MERCADO PAGO']};
            const disponibles = filasPago.filter(fila => nombres[evento.key].includes(fila.dataset.nombre));
            const siguiente = evento.key === 'F7' && disponibles.length > 1
                ? disponibles[(disponibles.indexOf(medioSeleccionado) + 1) % disponibles.length]
                : disponibles[0];
            if (cobroAbierto) elegirMedio(siguiente);
            else if (siguiente) abrirCobro(siguiente);
        } else if (evento.key === 'F8') {
            evento.preventDefault();
            if (!cobroAbierto) abrirCobro();
            else if (!confirmarBoton.disabled) confirmarBoton.click();
            else enfocarMedio();
        } else if (evento.key === 'Escape') {
            evento.preventDefault();
            if (cobroAbierto) volverVenta();
            else { seccionResultados.hidden = true; codigo.focus(); }
        } else if (evento.key === 'Delete' && !cobroAbierto && !escribiendo && seleccionada !== null) {
            evento.preventDefault();
            quitarLinea(seleccionada);
        }
    });
    dibujarCarrito();
    codigo.focus();
}

iniciarPos();
