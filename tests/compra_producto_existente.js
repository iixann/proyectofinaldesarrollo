/* Simulación de DOM/fetch: sin navegador, servidor ni base de datos.
 * Con Node: node tests/compra_producto_existente.js
 */
async function probarCompraProductoExistente(codigoCompra) {
    const checks = [];
    function check(ok, label) { if (!ok) throw new Error(label); checks.push(label); }
    function crearEntorno(sinCategorias = false, recepcion = false) {
        class Elemento {
            constructor(tag = 'div', attrs = {}) {
                this.tagName = tag; this.attrs = attrs; this.children = []; this.events = {}; this.dataset = {};
                this.value = ''; this.hidden = false; this.disabled = false; this.textContent = ''; this.isConnected = true;
                const clases = new Set();
                this.classList = {add: c => clases.add(c), remove: c => clases.delete(c), contains: c => clases.has(c), toggle: (c, yes) => yes ? clases.add(c) : clases.delete(c)};
            }
            appendChild(el) { el.parent = this; this.children.push(el); return el; }
            add(el) { this.appendChild(el); }
            get options() { return this.children; }
            get rows() { return this.children; }
            replaceChildren(...els) { this.children = []; els.forEach(el => this.appendChild(el)); }
            matches(s) {
                if (['select', 'input', 'tr', 'fieldset'].includes(s)) return this.tagName === s;
                if (s === '[name]') return !!this.name;
                if (s === 'button[type="submit"]') return this.tagName === 'button' && this.attrs.type === 'submit';
                const campo = /^input\[name\$="(.+)"\]$/.exec(s);
                if (campo) return this.tagName === 'input' && this.name.endsWith(campo[1]);
                return s.startsWith('[') && s.endsWith(']') && s.slice(1, -1) in this.attrs;
            }
            querySelectorAll(s) { return this.children.flatMap(el => [el, ...el.querySelectorAll('*')]).filter(el => s === '*' || el.matches(s)); }
            querySelector(s) { return this.querySelectorAll(s)[0] || null; }
            closest(s) { if (s === '.modal' && this.tagName === 'modal') return this; if (this.matches(s)) return this; return this.parent?.closest(s) || null; }
            addEventListener(k, f) { (this.events[k] ??= []).push(f); }
            dispatchEvent(e) { this.fire(e.type, e); }
            fire(k, e = {}) { (this.events[k] || []).forEach(f => f({target: this, currentTarget: this, preventDefault() {}, ...e})); }
            cloneNode() { const el = new Elemento(this.tagName, {...this.attrs}); el.name = this.name; el.value = this.value; this.children.forEach(child => el.appendChild(child.cloneNode())); return el; }
            reset() { Object.values(this.elements || {}).forEach(el => { el.value = ''; }); }
        }
        const ids = {};
        const add = (id, tag = 'div') => ids[id] = new Elemento(tag);
        add('datosProductosCompra').textContent = JSON.stringify([{id_producto: 1, nombre_producto: 'loco un poco', id_proveedor: 1}]);
        const compra = add('formularioCompra', 'form'); compra.dataset.contexto = recepcion ? 'recepcion' : 'compra'; compra.elements = {csrf_compra: {value: 'token-fixture'}};
        const proveedor = add('id_proveedor', 'select'); proveedor.value = '2';
        const cuerpo = add('renglonesCompra', 'tbody'); const fila = cuerpo.appendChild(new Elemento('tr'));
        const selector = fila.appendChild(new Elemento('select')); selector.name = 'renglones[0][id_producto]';
        for (const campo of ['cantidad', 'precio_unitario']) { const el = fila.appendChild(new Elemento('input')); el.name = 'renglones[0][' + campo + ']'; el.value = campo === 'cantidad' ? '4' : '100.00'; }
        const nuevo = fila.appendChild(new Elemento('button', {'data-nuevo-producto': ''}));
        const producto = add('formularioProductoRapido', 'form'); producto.action = '/proyectofinaldesarrollo/compra/crearProductoRapido'; producto.elements = {};
        const camposNuevos = new Elemento('fieldset', {'data-datos-nuevo-producto': ''}); producto.appendChild(camposNuevos);
        for (const name of ['id_proveedor', 'codigo_barras', 'nombre_producto', 'id_categoria', 'precio_venta', 'stock_minimo', 'descripcion']) {
            const el = new Elemento('input'); el.name = name; producto.elements[name] = el;
            (['id_proveedor', 'codigo_barras'].includes(name) ? producto : camposNuevos).appendChild(el);
        }
        const boton = producto.appendChild(new Elemento('button', {type: 'submit'})); boton.disabled = sinCategorias;
        const error = producto.appendChild(new Elemento('p', {'data-error': ''}));
        const formProv = add('formularioProveedorRapido', 'form'); formProv.appendChild(new Elemento('button', {type: 'submit'})); formProv.appendChild(new Elemento('p', {'data-error': ''}));
        const modal = add('modalProductoRapido', 'modal'); modal.appendChild(producto);
        add('modalProveedorRapido', 'modal').appendChild(formProv);
        const panel = add('productoExistenteCompra');
        for (const marker of ['data-existente-nombre', 'data-existente-ean', 'data-existente-mensaje']) panel.appendChild(new Elemento('span', {[marker]: ''}));
        for (const id of ['registrarCompra', 'mensajeCompra', 'agregarRenglon', 'avisoMaestros']) add(id);
        if (!recepcion) add('consultarEanCompra', 'button');
        const requests = [];
        let responder = () => ({ok: true, existe: false});
        const fetch = async (url, options) => {
            requests.push({url, body: options.body.values});
            const payload = responder(url, options);
            return {ok: !payload.falla, redirected: !!payload.redirected, json: async () => payload};
        };
        class FormDataFixture {
            constructor(form) { this.values = {}; Object.entries(form.elements || {}).forEach(([key, el]) => { if (!el.disabled && !el.closest('fieldset')?.disabled) this.values[key] = el.value; }); }
            set(k, value) { this.values[k] = value; }
        }
        const instancias = new Map();
        const bootstrap = {Modal: {getOrCreateInstance(el) { if (!instancias.has(el)) instancias.set(el, {show() { el.visible = true; }, hide() { el.visible = false; }}); return instancias.get(el); }}};
        const document = {getElementById: id => ids[id] || null};
        new Function('document', 'bootstrap', 'fetch', 'FormData', 'Option', 'Event', codigoCompra)(document, bootstrap, fetch, FormDataFixture, function (text, value) { const el = new Elemento('option'); el.textContent = text; el.value = value; return el; }, class { constructor(type) { this.type = type; } });
        return {ids, producto, boton, panel, camposNuevos, fila, selector, error, requests, modal,
            open() { cuerpo.fire('click', {target: nuevo}); },
            response(fn) { responder = fn; },
            async consultar(payload) { responder = () => payload; producto.elements.codigo_barras.value = '0000000000001'; ids.consultarEanCompra.fire('click'); await flush(); },
            async enviar() { producto.fire('submit'); await flush(); }};
    }
    async function flush() { for (let i = 0; i < 20; i++) await Promise.resolve(); }
    const existente = {id_producto: 1, id_proveedor: 2, codigo_barras: '0000000000001', nombre_producto: 'loco un poco', activo: true, asociado: false};
    const env = crearEntorno(); env.open();
    await env.consultar({ok: true, existe: true, producto: existente});
    check(!env.panel.hidden && env.camposNuevos.hidden && env.camposNuevos.disabled && env.boton.textContent === 'Asociar producto', 'Existente oculta los campos de alta y ofrece asociación');
    check(env.requests.length === 1 && env.requests[0].url.endsWith('consultarProductoRapido'), 'Consultar/cancelar no envía una asociación');
    env.response(() => ({ok: true, ya_asociado: false, producto: existente}));
    env.producto.fire('submit'); env.producto.fire('submit'); await flush();
    check(env.requests.filter(r => r.url.endsWith('asociarProductoRapido')).length === 1, 'Doble envío de asociación bloqueado');
    const request = env.requests[1];
    check(request.body.id_producto === '1' && request.body.id_proveedor === '2' && request.body.codigo_barras === '0000000000001' && request.body.csrf_compra === 'token-fixture' && !('nombre_producto' in request.body), 'Confirmación envía ID, proveedor, EAN y CSRF, sin datos de edición');
    check(env.selector.value === '1' && env.selector.options.some(p => p.value === '1') && !env.ids.registrarCompra.disabled && !env.modal.visible, 'Queda disponible, seleccionado y permite continuar Compra');
    check(env.fila.querySelector('input').value === '4', 'Asociar conserva cantidades/costos ya escritos');
    env.open(); await env.consultar({ok: true, existe: true, producto: {...existente, asociado: true}});
    check(env.boton.disabled && env.panel.querySelector('[data-existente-mensaje]').textContent.includes('ya está asociado'), 'Ya asociado muestra mensaje y bloquea confirmación');
    check(env.selector.options.filter(p => p.value === '1').length === 1, 'No duplica opciones al consultar una relación existente');
    env.open(); await env.consultar({ok: true, existe: true, producto: {...existente, activo: false}});
    check(env.boton.disabled && env.panel.querySelector('[data-existente-mensaje]').textContent.includes('inactivo'), 'Inactivo no ofrece reactivación ni asociación');
    env.open(); await env.consultar({ok: true, existe: false, producto: null});
    check(env.panel.hidden && !env.camposNuevos.hidden && !env.camposNuevos.disabled && !env.boton.disabled, 'EAN nuevo mantiene el formulario de creación');
    env.response(() => ({ok: true, producto: {id_producto: 3, id_proveedor: 2, nombre_producto: 'Nuevo'}}));
    await env.enviar();
    check(env.requests.at(-1).url.endsWith('crearProductoRapido') && env.selector.value === '3', 'Crear nuevo sigue usando el endpoint habitual y selecciona la línea');
    env.open(); env.producto.elements.codigo_barras.value = '0000000000001';
    env.response(() => ({ok: false, falla: true, existe: true, producto: existente}));
    await env.enviar();
    check(!env.panel.hidden && env.boton.textContent === 'Asociar producto' && env.modal.visible, 'Conflicto EAN en alta también ofrece asociación y no cierra el modal');
    env.response(() => ({ok: false, falla: true, mensaje: 'Proveedor inactivo'})); await env.enviar();
    check(env.error.textContent === 'Proveedor inactivo' && !env.error.classList.contains('d-none'), 'Errores de backend se muestran sin modificar la compra');
    const sinCategorias = crearEntorno(true); sinCategorias.open();
    await sinCategorias.consultar({ok: true, existe: true, producto: existente});
    check(!sinCategorias.boton.disabled, 'Puede asociar un existente aunque no haya categorías para crear nuevos');
    const recepcion = crearEntorno(false, true); recepcion.open();
    recepcion.response(() => ({ok: true, producto: {id_producto: 5, id_proveedor: 2, nombre_producto: 'Nuevo recepción'}}));
    await recepcion.enviar();
    check(recepcion.requests[0].url.endsWith('crearProductoRapido') && recepcion.selector.value === '5', 'Alta compartida de Recepción conserva su flujo anterior');
    return {resultado: 'OK', comprobaciones: checks.length, detalles: checks};
}
if (typeof module !== 'undefined' && typeof require === 'function') {
    probarCompraProductoExistente(require('fs').readFileSync(require('path').join(__dirname, '../public/js/compra.js'), 'utf8'))
        .then(result => console.log(JSON.stringify(result, null, 2)))
        .catch(error => { console.error(error); process.exitCode = 1; });
}
