<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de proveedores | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'proveedores'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <?php if (\app\controllers\SessionController::esAdministrador()): ?>
            <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/admin/index">Volver al panel principal</a>
        <?php endif; ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
            <h1 class="h2 fw-bold mb-0">Gestión de proveedores</h1>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/proveedor/crear">Nuevo proveedor</a>
        </div>
        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4" aria-label="Listado de proveedores">
            <div class="card-body p-3 p-md-4">
                <?php if (!$proveedores): ?>
                    <p class="text-secondary text-center my-4">No hay proveedores registrados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Razón social</th><th scope="col">CUIT</th><th scope="col">Contacto</th><th scope="col">Teléfono</th><th scope="col">Email</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($proveedores as $proveedor): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $proveedor->id_proveedor, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($proveedor->razon_social ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($proveedor->cuit ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($proveedor->nombre_contacto ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($proveedor->telefono ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($proveedor->email ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge rounded-pill <?= (int) $proveedor->estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int) $proveedor->estado === 1 ? 'ACTIVO' : 'INACTIVO' ?></span></td>
                                        <td><div class="d-flex flex-wrap gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/proveedor/editar/<?= htmlspecialchars((string) $proveedor->id_proveedor, ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                                            <?php if ((int) $proveedor->estado === 1): ?>
                                                <form method="post" action="/proyectofinaldesarrollo/proveedor/baja/<?= htmlspecialchars((string) $proveedor->id_proveedor, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit">Dar de baja</button>
                                                </form>
                                            <?php endif; ?>
                                        </div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
