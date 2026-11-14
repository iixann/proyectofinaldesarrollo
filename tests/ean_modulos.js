// Verifica los adaptadores en las vistas reales sin enviar formularios al backend.
async function probarModuloEan(modulo) {
    const comprobaciones = [];
    const boton = document.querySelector('[data-escanear-ean]');
    const abrirOriginal = window.EanScanner.abrir;
    let codigo = '4006381333931';
    let aperturas = 0;
    let envios = 0;
    const peticiones = [];
    const fetchOriginal = window.fetch;

    // Identifica el caso que falló en la pantalla correspondiente.
    function comprobar(condicion, mensaje) {
        if (!condicion) throw new Error(modulo + ': ' + mensaje);
        comprobaciones.push(mensaje);
    }

    // Solo simula la entrega del código; el adaptador del módulo es el real.
    window.EanScanner.abrir = callback => { aperturas++; callback(codigo); return true; };
    document.querySelectorAll('form').forEach(formulario => {
        formulario.addEventListener('submit', evento => { evento.preventDefault(); envios++; });
    });
    try {
        if (modulo === 'recepcion') {
            const fila = document.querySelector('[data-ean-recepcion]');
            const cantidad = fila.querySelector('input[type="number"]');
            cantidad.value = '3';
            boton.click();
            comprobar(fila.classList.contains('ean-localizado') && document.activeElement === cantidad, 'Localiza y enfoca el producto que pertenece a la recepción');
            comprobar(cantidad.value === '3' && envios === 0, 'No cambia la cantidad ni confirma la recepción');
            codigo = '96385074';
            boton.click();
            comprobar(document.getElementById('mensajeEanRecepcion').textContent === 'El producto escaneado no pertenece a esta recepción.', 'Informa el producto ajeno a la recepción');
        } else if (['stock', 'reposicion'].includes(modulo)) {
            const campo = document.getElementById('codigo_barras');
            const formulario = campo.form;
            formulario.elements.nombre.value = 'Filtro anterior';
            boton.click();
            comprobar(campo.value === codigo && formulario.elements.escaneado.value === '1', 'Entrega el EAN como string al filtro GET');
            comprobar(formulario.elements.nombre.value === '' && formulario.elements.id_categoria.value === '' && formulario.elements.id_proveedor.value === '', 'Limpia filtros anteriores para no ocultar el producto');
            comprobar(envios === 1 && formulario.method === 'get', 'Solo solicita la consulta existente por GET');
            if (modulo === 'stock') comprobar(document.querySelector('[data-ean-resumen]').textContent.includes('Stock mínimo'), 'La tarjeta móvil muestra existencias y mínimo');
        } else if (['compra', 'directa'].includes(modulo)) {
            const proveedor = document.getElementById('id_proveedor');
            boton.click();
            comprobar(aperturas === 0, 'Exige seleccionar proveedor antes de abrir cámara');
            proveedor.value = '1';
            proveedor.dispatchEvent(new Event('change'));
            // La red devuelve el producto; el flujo de selección sigue siendo el real.
            window.fetch = async (url, opciones) => {
                peticiones.push({url, codigo: opciones.body.get('codigo_barras')});
                return {ok: true, redirected: false, json: async () => ({ok: true, existe: true, producto: {
                    id_producto: 1, id_proveedor: 1, nombre_producto: 'Malbec de prueba', codigo_barras: codigo, activo: true, asociado: true
                }})};
            };
            const cantidad = document.querySelector('#renglonesCompra input[type="number"]');
            cantidad.value = '4';
            boton.click();
            await new Promise(resolve => setTimeout(resolve, 100));
            comprobar(document.querySelector('#renglonesCompra select').value === '1' && cantidad.value === '4', 'Selecciona el producto y conserva la cantidad ya escrita');
            comprobar(peticiones.length === 1 && peticiones[0].url.endsWith('/compra/consultarProductoRapido') && peticiones[0].codigo === codigo, 'Solo llama a la consulta existente con el EAN detectado');
            comprobar(envios === 0, 'No registra compra ni confirma recepción al escanear');
        }
        return {modulo, comprobaciones: comprobaciones.length, detalles: comprobaciones};
    } finally {
        window.EanScanner.abrir = abrirOriginal;
        window.fetch = fetchOriginal;
    }
}
