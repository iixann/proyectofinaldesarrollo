<?php
$esAdministrador = \app\controllers\SessionController::esAdministrador();
$puedeAdministrarCategorias = \app\controllers\SessionController::sesionValida()
    && ($esAdministrador || in_array('DUEÑO', $_SESSION['usuario']['roles'], true));
$inicioNavegacion = $esAdministrador ? '/proyectofinaldesarrollo/admin/index' : '/proyectofinaldesarrollo/categoria/index';
?>
    <a class="visually-hidden-focusable position-absolute bg-white p-3" href="#contenido">Ir al contenido</a>
    <header>
        <nav class="navbar navbar-expand-xl bg-white shadow-sm py-3" aria-label="Navegación principal">
            <div class="container">
                <a class="navbar-brand marca-sistema fw-semibold text-primary d-flex align-items-center gap-2" href="<?= $inicioNavegacion ?>">
                    <i class="fa-solid fa-wine-glass" aria-hidden="true"></i>
                    <span>Sistema de Gestión de Vinería</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navegacionPrincipal" aria-controls="navegacionPrincipal" aria-expanded="false" aria-label="Abrir o cerrar menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse mt-3 mt-xl-0" id="navegacionPrincipal">
                    <ul class="navbar-nav ms-auto align-items-xl-center gap-xl-1">
                        <?php if ($esAdministrador): ?>
                        <li class="nav-item"><a class="nav-link <?= $seccionActual === 'inicio' ? 'active text-primary fw-semibold' : '' ?>" <?= $seccionActual === 'inicio' ? 'aria-current="page"' : '' ?> href="/proyectofinaldesarrollo/admin/index">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link <?= $seccionActual === 'usuarios' ? 'active text-primary fw-semibold' : '' ?>" <?= $seccionActual === 'usuarios' ? 'aria-current="page"' : '' ?> href="/proyectofinaldesarrollo/user/index">Usuarios</a></li>
                        <?php endif; ?>
                        <?php if ($puedeAdministrarCategorias): ?>
                        <li class="nav-item"><a class="nav-link <?= $seccionActual === 'categorias' ? 'active text-primary fw-semibold' : '' ?>" <?= $seccionActual === 'categorias' ? 'aria-current="page"' : '' ?> href="/proyectofinaldesarrollo/categoria/index">Categorías</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><span class="nav-link disabled" aria-disabled="true" title="Próximamente">Productos<span class="visually-hidden">: próximamente</span></span></li>
                        <li class="nav-item"><span class="nav-link disabled" aria-disabled="true" title="Próximamente">Proveedores<span class="visually-hidden">: próximamente</span></span></li>
                        <li class="nav-item"><span class="nav-link disabled" aria-disabled="true" title="Próximamente">Compras<span class="visually-hidden">: próximamente</span></span></li>
                        <li class="nav-item"><span class="nav-link disabled" aria-disabled="true" title="Próximamente">Stock<span class="visually-hidden">: próximamente</span></span></li>
                        <li class="nav-item"><span class="nav-link disabled" aria-disabled="true" title="Próximamente">Ventas<span class="visually-hidden">: próximamente</span></span></li>
                        <li class="nav-item ms-xl-2 mt-2 mt-xl-0">
                            <a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/session/logout"><i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>Cerrar sesión</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
