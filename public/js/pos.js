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
    let aplicado = 0;
    const pagos = [];
    const usados = new Set();
    for (const entrada of entradas) {
        if (entrada.monto.trim() === '') continue;
        const monto = precioCentavosPos(entrada.monto.trim());
        if (monto === 0) continue;
        if (usados.has(entrada.id_medio_pago)) throw new Error('No repitas un medio de pago.');
        usados.add(entrada.id_medio_pago);
        aplicado += monto;
        if (!Number.isSafeInteger(aplicado) || aplicado > 999999999999) throw new Error('Los pagos superan el máximo permitido.');
        const pago = {id_medio_pago: entrada.id_medio_pago, monto: decimalPos(monto), referencia: entrada.referencia || ''};
        if (entrada.efectivo) {
            const entregado = precioCentavosPos(entrada.entregado.trim());
            if (entregado < monto) throw new Error('El efectivo entregado no alcanza para el monto aplicado.');
            pago.monto_entregado = decimalPos(entregado);
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
    let seleccionada = null;
    let ultimaAgregada = null;
    let temporizadorResaltado;
    const confirmarBoton = document.getElementById('confirmarVenta');
    const filasPago = Array.from(document.querySelectorAll('#filasPago [data-medio]'));
    const estadoPagos = document.getElementById('estadoPagos');
    const botonesMedio = Array.from(document.querySelectorAll('[data-elegir-medio]'));
    let medioSeleccionado = null;
    function elegirMedio(fila, enfocar = true) {
        if (!fila || confirmando || !sesionDisponible) return;
        medioSeleccionado = fila;
        filasPago.forEach(medio => { medio.hidden = medio !== fila; });
        botonesMedio.forEach(boton => {
            const activo = boton.dataset.elegirMedio === fila.dataset.medio;
            boton.classList.toggle('active', activo);
            boton.setAttribute('aria-expanded', activo ? 'true' : 'false');
        });
        if (enfocar) (fila.querySelector('[data-entregado]') || fila.querySelector('[data-monto]')).focus();
    }
    botonesMedio.forEach(boton => boton.addEventListener('click', () => {
        elegirMedio(filasPago.find(fila => fila.dataset.medio === boton.dataset.elegirMedio));
    }));
    elegirMedio(filasPago.find(fila => fila.dataset.efectivo === '1') || filasPago[0], false);
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
                lista.appendChild(item);
            } catch (error) { /* El validador de cobro muestra el importe inválido. */ }
        });
        lista.hidden = lista.children.length === 0;
    }

    function informar(texto, error = false) {
        mensaje.textContent = texto;
        mensaje.className = 'alert ' + (error ? 'alert-warning' : 'alert-info');
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
            celda(fila, '').appendChild(boton('Eliminar', 'Eliminar ' + linea.nombre_producto, function () {
                quitarLinea(linea.id_producto);
            }, 'btn-outline-danger'));
            cuerpo.appendChild(fila);
        });
        document.getElementById('totalCarrito').textContent = formatoDineroPos(carrito.totalCentavos());
        const unidades = lineas.reduce((suma, linea) => suma + linea.cantidad, 0);
        document.getElementById('cantidadArticulos').textContent = unidades + (unidades === 1 ? ' artículo' : ' artículos');
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
        dibujarResumenPagos();
        confirmarBoton.disabled = true;
        document.getElementById('totalPagado').textContent = '$ 0,00';
        filasPago.forEach(fila => {
            const vuelto = fila.querySelector('[data-vuelto]');
            if (vuelto) {
                vuelto.textContent = 'Vuelto: $ 0,00';
                try {
                    const monto = precioCentavosPos(fila.querySelector('[data-monto]').value || '0');
                    const entregado = precioCentavosPos(fila.querySelector('[data-entregado]').value || '0');
                    if (entregado >= monto) vuelto.textContent = 'Vuelto: ' + formatoDineroPos(entregado - monto);
                } catch (error) { vuelto.textContent = 'Revisá los importes de efectivo.'; }
            }
        });
        try {
            const resumen = resumirPagosPos(entradasPago(), carrito.totalCentavos());
            document.getElementById('totalPagado').textContent = formatoDineroPos(resumen.aplicado);
            if (!carrito.lineas().length) { estadoPagos.textContent = 'Agregá productos para iniciar el cobro.'; return; }
            if (resumen.diferencia > 0) estadoPagos.textContent = 'Falta aplicar: ' + formatoDineroPos(resumen.diferencia);
            else if (resumen.diferencia < 0) estadoPagos.textContent = 'Los pagos exceden el total en ' + formatoDineroPos(-resumen.diferencia) + '. Ajustá el monto aplicado; el vuelto se calcula sobre el entregado.';
            else estadoPagos.textContent = 'El total está cubierto.';
            confirmarBoton.disabled = confirmando || pendientes > 0 || !sesionDisponible || !resumen.pagos.length || resumen.diferencia !== 0;
        } catch (error) { estadoPagos.textContent = error.message; }
    }
    function limpiarPagos() {
        filasPago.forEach(fila => fila.querySelectorAll('input').forEach(input => { input.value = ''; }));
    }
    filasPago.forEach(fila => fila.addEventListener('input', actualizarCobro));
    confirmarBoton.addEventListener('click', async function () {
        if (confirmando || pendientes > 0 || !sesionDisponible || !carrito.lineas().length) return;
        let resumen;
        try {
            resumen = resumirPagosPos(entradasPago(), carrito.totalCentavos());
            if (!resumen.pagos.length || resumen.diferencia !== 0) throw new Error('Los pagos deben cubrir exactamente el total.');
        } catch (error) { informar(error.message, true); return; }
        confirmando = true;
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
            const enlaceVenta = document.getElementById('verUltimaVenta');
            enlaceVenta.href = '/proyectofinaldesarrollo/venta/ver/' + datos.id_venta;
            enlaceVenta.hidden = false;
            informar('Venta #' + datos.id_venta + ' registrada correctamente. Total: $ ' + datos.total);
        } catch (error) {
            informar(error.message || 'No se recibió confirmación de la venta. No se reenvió automáticamente.', true);
        } finally {
            confirmando = false;
            terminal.querySelectorAll('input, button').forEach(elemento => { elemento.disabled = !sesionDisponible; });
            document.getElementById('vaciarCarrito').disabled = false;
            confirmarBoton.textContent = 'COBRAR';
            dibujarCarrito();
            if (sesionDisponible) codigo.focus();
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
        if (confirmando || !sesionDisponible || evento.ctrlKey || evento.altKey || evento.metaKey) return;
        const escribiendo = evento.target.closest('input, textarea, select, [contenteditable="true"]');
        if (evento.key === 'F2') {
            evento.preventDefault();
            codigo.focus();
        } else if (evento.key === 'F3') {
            evento.preventDefault();
            document.getElementById('nombre').focus();
        } else if (['F4', 'F5', 'F6', 'F7'].includes(evento.key)) {
            evento.preventDefault();
            const nombres = {F4: ['EFECTIVO'], F5: ['DÉBITO'], F6: ['CRÉDITO'], F7: ['TRANSFERENCIA', 'MERCADO PAGO']};
            const disponibles = filasPago.filter(fila => nombres[evento.key].includes(fila.dataset.nombre));
            const siguiente = evento.key === 'F7' && disponibles.length > 1
                ? disponibles[(disponibles.indexOf(medioSeleccionado) + 1) % disponibles.length]
                : disponibles[0];
            elegirMedio(siguiente);
        } else if (evento.key === 'F8') {
            evento.preventDefault();
            if (!confirmarBoton.disabled) confirmarBoton.click();
            else if (carrito.lineas().length) {
                elegirMedio(medioSeleccionado || filasPago[0], false);
                medioSeleccionado?.querySelector('[data-monto]').focus();
            }
            else codigo.focus();
        } else if (evento.key === 'Escape') {
            evento.preventDefault();
            seccionResultados.hidden = true;
            codigo.focus();
        } else if (evento.key === 'Delete' && !escribiendo && seleccionada !== null) {
            evento.preventDefault();
            quitarLinea(seleccionada);
        }
    });
    dibujarCarrito();
}

iniciarPos();
