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
        .form-control:focus, .form-check-input:focus {
            border-color: #b98094;
            box-shadow: 0 0 0 .25rem var(--bs-focus-ring-color);
        }
        .form-check-input:checked { background-color: var(--bs-primary); border-color: var(--bs-primary); }
        body { background-color: #f4f6f8; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #334155; }
        .marca-sistema { font-size: 1rem; white-space: normal; }
        .icono-modulo { width: 3rem; height: 3rem; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; }
        .tarjeta-modulo { min-height: 15rem; }
        .navbar .nav-link.disabled { color: #64748b; }
    </style>
