// Prepara filas y un total orientativo; el modelo confirma todos los importes.
(() => {
    'use strict';
    const formulario = document.getElementById('formRectificacion');
    if (!formulario) return;
    const cuerpo = document.getElementById('renglonesRectificacion');
    let indice = cuerpo.rows.length;
    // Calcula en centavos para evitar redondeos de importes decimales.
    function actualizarTotal() {
        let total = 0;
        for (const fila of cuerpo.rows) {
            const precio = fila.querySelector('select').selectedOptions[0]?.dataset.precio || '0';
            const partes = /^(\d{1,10})(?:\.(\d{1,2}))?$/.exec(precio);
            const cantidad = Number(fila.querySelector('input').value);
            if (!partes || !Number.isInteger(cantidad) || cantidad < 1) { total = NaN; break; }
            total += (Number(partes[1]) * 100 + Number((partes[2] || '').padEnd(2, '0'))) * cantidad;
        }
        document.getElementById('totalRectificacion').textContent = Number.isSafeInteger(total) && total <= 999999999999
            ? new Intl.NumberFormat('es-AR', {style: 'currency', currency: 'ARS'}).format(total / 100) : 'Revisá productos y cantidades';
    }
    // Genera nombres únicos para que PHP reciba todas las líneas.
    document.getElementById('agregarRectificacion').addEventListener('click', () => {
        const plantilla = document.getElementById('plantillaRectificacion').content.cloneNode(true);
        plantilla.querySelectorAll('[name]').forEach(campo => { campo.name = campo.name.replace('__INDICE__', String(indice)); });
        indice++;
        cuerpo.appendChild(plantilla);
        actualizarTotal();
    });
    // Quitar una fila del borrador no modifica la venta original.
    cuerpo.addEventListener('click', evento => {
        if (evento.target.closest('[data-quitar-rectificacion]')) { evento.target.closest('tr').remove(); actualizarTotal(); }
    });
    formulario.addEventListener('input', actualizarTotal);
    formulario.addEventListener('change', actualizarTotal);
    // Evita envíos repetidos por doble clic, sin reemplazar los bloqueos del modelo.
    formulario.addEventListener('submit', () => { formulario.querySelector('button[type="submit"]').disabled = true; });
    actualizarTotal();
})();
