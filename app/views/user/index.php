<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de usuarios | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
    <link rel="stylesheet" href="/proyectofinaldesarrollo/css/usuarios.css?v=<?= substr(hash_file('sha256','public/css/usuarios.css'),0,12) ?>">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'usuarios'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <!-- Muestra los datos de usuarios enviados por el controlador. -->
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/admin/index"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Volver al panel principal</a>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
            <div>
                <h1 class="h2 fw-bold mb-2">Gestión de usuarios</h1>
                <p class="text-secondary mb-0">Consultá los usuarios del sistema y sus roles asignados.</p>
            </div>
            <a class="btn btn-primary flex-shrink-0" href="/proyectofinaldesarrollo/user/crear"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Crear usuario</a>
        </div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4" aria-label="Listado de usuarios">
            <?php if (empty($usuarios)): ?>
                <div class="card-body text-center p-5">
                    <i class="fa-solid fa-users text-secondary fs-2 mb-3" aria-hidden="true"></i>
                    <p class="text-secondary mb-0">No hay usuarios registrados.</p>
                </div>
            <?php else: ?>
                <div class="card-body p-3 p-md-4">
                    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p>
                        <!-- Listado generado con los registros recibidos del controlador. -->
                        <table class="table table-hover align-middle mb-0 tabla-usuarios">
                            <caption class="visually-hidden">Usuarios, estado y roles asignados</caption>
                            <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Nombre</th><th scope="col">Apellido</th><th scope="col">Email</th><th scope="col">Estado</th><th scope="col">Roles</th><th scope="col" class="col-acciones-usuario">Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $usuario->id_usuario, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($usuario->nombre, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($usuario->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($usuario->email, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge rounded-pill text-nowrap <?= (int) $usuario->estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int) $usuario->estado === 1 ? 'ACTIVO' : 'INACTIVO' ?></span></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($usuario->roles as $rol): ?>
                                                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis text-nowrap"><?= htmlspecialchars($rol->nombre_rol, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endforeach; ?>
                                                <?php if ((int) $usuario->es_principal === 1): ?><span class="badge text-bg-primary text-nowrap" title="Administrador principal">Principal</span><?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="col-acciones-usuario">
                                            <?php if ((int) $usuario->es_principal === 1): ?>
                                                <!-- El principal no ofrece acciones administrativas sensibles. -->
                                                <span class="small text-secondary text-nowrap">Principal protegido</span>
                                            <?php else: ?><div class="dropdown">
                                            <!-- Solo ofrece acciones reales y mantiene las autorizaciones del servidor. -->
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-usuario-acciones aria-expanded="false" aria-label="Acciones de <?= htmlspecialchars($usuario->nombre . ' ' . $usuario->apellido, ENT_QUOTES, 'UTF-8') ?>">Acciones</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if ($usuario->puede_cambiar_roles): ?><li><a class="dropdown-item" href="/proyectofinaldesarrollo/user/cambiarRoles/<?= (int) $usuario->id_usuario ?>">Cambiar roles</a></li><?php endif; ?>
                                                <li><a class="dropdown-item" href="/proyectofinaldesarrollo/user/restablecerPassword/<?= (int) $usuario->id_usuario ?>">Restablecer contraseña</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                    <li><form method="post" <?= (int) $usuario->estado === 1 ? 'data-confirmar-baja="¿Desea dar de baja al usuario ' . htmlspecialchars($usuario->nombre . ' ' . $usuario->apellido, ENT_QUOTES, 'UTF-8') . '?"' : '' ?> action="/proyectofinaldesarrollo/user/<?= (int) $usuario->estado === 1 ? 'baja' : 'reactivar' ?>/<?= (int) $usuario->id_usuario ?>">
                                                        <button class="dropdown-item <?= (int) $usuario->estado === 1 ? 'text-danger' : '' ?>" type="submit"><?= (int) $usuario->estado === 1 ? 'Dar de baja' : 'Reactivar' ?></button>
                                                    </form></li>
                                            </ul>
                                        </div><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <script src="/proyectofinaldesarrollo/js/usuarios.js?v=<?= substr(hash_file('sha256','public/js/usuarios.js'),0,12) ?>" defer></script>
</body>
</html>
