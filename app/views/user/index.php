<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de usuarios | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'usuarios'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/admin/index"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Volver al panel principal</a>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
            <div>
                <h1 class="h2 fw-bold mb-2">Gestión de usuarios</h1>
                <p class="text-secondary mb-0">Consultá los usuarios del sistema y sus roles asignados.</p>
            </div>
            <a class="btn btn-primary flex-shrink-0" href="/proyectofinaldesarrollo/user/crear"><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Crear usuario</a>
        </div>
        <section class="card border-0 shadow-sm rounded-4" aria-label="Listado de usuarios">
            <?php if (empty($usuarios)): ?>
                <div class="card-body text-center p-5">
                    <i class="fa-solid fa-users text-secondary fs-2 mb-3" aria-hidden="true"></i>
                    <p class="text-secondary mb-0">No hay usuarios registrados.</p>
                </div>
            <?php else: ?>
                <div class="card-body p-3 p-md-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <caption class="visually-hidden">Usuarios, estado y roles asignados</caption>
                            <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Nombre</th><th scope="col">Apellido</th><th scope="col">Email</th><th scope="col">Estado</th><th scope="col">Roles</th></tr></thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $usuario->id_usuario, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($usuario->nombre, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($usuario->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($usuario->email, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge rounded-pill <?= (int) $usuario->estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int) $usuario->estado === 1 ? 'ACTIVO' : 'INACTIVO' ?></span></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($usuario->roles as $rol): ?>
                                                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis"><?= htmlspecialchars($rol->nombre_rol, ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
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
</body>
</html>
