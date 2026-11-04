    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #6D1A36;
            --bs-primary-rgb: 109, 26, 54;
            --bs-primary-text-emphasis: #501328;
            --bs-primary-bg-subtle: #f4e8ec;
            --bs-primary-border-subtle: #d9b6c2;
            --bs-link-color: #6D1A36;
            --bs-link-color-rgb: 109, 26, 54;
            --bs-link-hover-color: #501328;
            --bs-link-hover-color-rgb: 80, 19, 40;
            --bs-focus-ring-color: rgba(109, 26, 54, .25);
        }
        .btn-primary {
            --bs-btn-bg: var(--bs-primary);
            --bs-btn-border-color: var(--bs-primary);
            --bs-btn-hover-bg: #58152c;
            --bs-btn-hover-border-color: #58152c;
            --bs-btn-active-bg: #501328;
            --bs-btn-active-border-color: #501328;
            --bs-btn-disabled-bg: var(--bs-primary);
            --bs-btn-disabled-border-color: var(--bs-primary);
            --bs-btn-focus-shadow-rgb: var(--bs-primary-rgb);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--bs-primary);
            --bs-btn-border-color: var(--bs-primary);
            --bs-btn-hover-bg: var(--bs-primary);
            --bs-btn-hover-border-color: var(--bs-primary);
            --bs-btn-active-bg: #501328;
            --bs-btn-active-border-color: #501328;
            --bs-btn-disabled-color: var(--bs-primary);
            --bs-btn-disabled-border-color: var(--bs-primary);
            --bs-btn-focus-shadow-rgb: var(--bs-primary-rgb);
        }
        .link-primary:hover, .link-primary:focus { color: var(--bs-link-hover-color) !important; }
        .form-control:focus, .form-select:focus, .form-check-input:focus {
            border-color: #b98094;
            box-shadow: 0 0 0 .25rem var(--bs-focus-ring-color);
        }
        .form-check-input:checked { background-color: var(--bs-primary); border-color: var(--bs-primary); }
        body { background-color: #f4f6f8; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #334155; }
        .marca-sistema { font-size: 1rem; white-space: normal; flex-shrink: 0; }
        .icono-modulo { width: 3rem; height: 3rem; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .tarjeta-modulo { min-height: 15rem; }
        .navbar .nav-link.disabled { color: #64748b; }
        main, .row > *, .card-body { min-width: 0; }
        main p, main dd, .alert { overflow-wrap: anywhere; }
        .form-control, .form-select { min-height: 2.5rem; }
        textarea.form-control { min-height: 5rem; }
        main .btn, .modal .btn { min-height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
        main .btn-sm { min-height: 2.25rem; }
        .badge { line-height: 1.35; white-space: normal; }
        .table { margin-bottom: 0; }
        .table > :not(caption) > * > * { padding: .8rem .75rem; }
        .table thead th { background: #f8fafc; color: #475569; font-size: .85rem; white-space: nowrap; }
        .table td { max-width: 24rem; overflow-wrap: anywhere; }
        .table-responsive { max-width: 100%; border: 1px solid #e2e8f0; border-radius: .65rem; scrollbar-width: thin; }
        .table-responsive:focus-visible { outline: 3px solid #b98094; outline-offset: 2px; }
        .table .acciones-tabla { min-width: 9rem; }
        .table .acciones-tabla .btn { white-space: nowrap; }
        .ayuda-tabla { display: none; }
        .filtros-listado { padding: .25rem .25rem 1rem; }
        .resumen-importes { border: 1px solid var(--bs-primary-border-subtle); background: var(--bs-primary-bg-subtle); border-radius: .75rem; padding: 1rem; }
        .resumen-importes output { font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 700; color: var(--bs-primary); overflow-wrap: anywhere; }
        .tabla-renglones select { min-width: 15rem; }
        .tabla-renglones input[type="number"], .tabla-recepcion input[type="number"] { min-width: 8rem; }
        .tabla-recepcion td:first-child { min-width: 11rem; }
        .cantidad-esperada { background: #f8fafc !important; text-align: center; font-weight: 600; }
        .modal-content > form { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
        .modal-body { overflow-y: auto; }
        .modal-footer { gap: .5rem; }
        .modal-footer > * { margin: 0; }
        .navbar .nav-link { border-radius: .4rem; }
        .navbar .nav-link.active { background: var(--bs-primary-bg-subtle); }
        #totalCarrito { font-size: clamp(1.75rem, 4vw, 2.5rem); overflow-wrap: anywhere; }
        #filasPago .form-control { min-width: 8rem; }
        #filasPago td:first-child { min-width: 6rem; }
        #filasPago label { display: block; margin-bottom: .25rem; }
        /* El JS existente agrega d-flex al td: mantener la alineación de la tabla. */
        #lineasCarrito td.d-flex { display: table-cell !important; white-space: nowrap; min-width: 11rem; }
        #lineasCarrito td.d-flex > * { display: inline-block; vertical-align: middle; }
        #lineasCarrito td.d-flex > * + * { margin-left: .35rem; }
        #lineasCarrito td:first-child { min-width: 10rem; }
        @media (max-width: 1199.98px) {
            .navbar-collapse { max-height: 70vh; overflow-y: auto; }
            .navbar .nav-link { padding: .7rem .75rem; }
            .navbar-brand { max-width: calc(100% - 4.5rem); }
        }
        @media (max-width: 767.98px) {
            .ayuda-tabla { display: block; margin: 0; padding: .5rem .75rem; color: #64748b; font-size: .8rem; background: #f8fafc; }
            .tarjeta-modulo { min-height: 0; }
            .tarjeta-modulo .card-body { padding: 1.25rem !important; }
            .table > :not(caption) > * > * { padding: .65rem; }
            .table td { font-size: .9rem; }
        }
        @media (max-width: 575.98px) {
            main .card-body.p-4, .modal-body { padding: 1rem !important; }
            .form-control, .form-select, .form-control-sm { font-size: 1rem; }
            .acciones-formulario > .btn { flex: 1 1 10rem; }
            .filtros-listado .btn { margin-top: .25rem; }
            .modal-footer .btn { flex: 1 1 8rem; }
            main dl dt { margin-bottom: .1rem; }
            main dl dd { margin-bottom: .85rem; }
        }
    </style>
