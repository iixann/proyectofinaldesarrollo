<!DOCTYPE html>
<html lang="es">
<head>
    <title>Gestión de categorías | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'categorias'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <?php if (\app\controllers\SessionController::esAdministrador()): ?>
            <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/admin/index">Volver al panel principal</a>
        <?php endif; ?>
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 my-4">
            <h1 class="h2 fw-bold mb-0">Gestión de categorías</h1>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/categoria/crear">Nueva categoría</a>
        </div>
        <?php if ($mensaje !== ''): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="get" action="/proyectofinaldesarrollo/categoria/index" class="filtros-listado row g-3 align-items-end mb-4">
            <div class="col-sm-4"><label class="form-label" for="busqueda">Nombre de categoría</label><input class="form-control" type="text" id="busqueda" name="busqueda" maxlength="150" value="<?= htmlspecialchars((string) $busqueda, ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Buscar</button> <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/categoria/index">Limpiar</a></div>
        </form>
        <section class="card border-0 shadow-sm rounded-4" aria-label="Listado de categorías">
            <div class="card-body p-3 p-md-4">
                <?php if (!$categorias): ?>
                    <p class="text-secondary text-center my-4">No se encontraron categorías. Probá otra búsqueda o creá una categoría.</p>
                <?php else: ?>
                    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla con desplazamiento horizontal"><p class="ayuda-tabla">Deslizá horizontalmente para ver todas las columnas.</p>
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th scope="col">ID</th><th scope="col">Nombre</th><th scope="col">Estado</th><th scope="col">Fecha de alta</th><th scope="col">Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($categorias as $categoria): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $categoria->id_categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($categoria->nombre_categoria, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="badge rounded-pill <?= (int) $categoria->estado === 1 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int) $categoria->estado === 1 ? 'ACTIVO' : 'INACTIVO' ?></span></td>
                                        <td><?= htmlspecialchars($categoria->fecha_alta, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><div class="acciones-tabla d-flex flex-wrap gap-2">
                                            <a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/categoria/editar/<?= htmlspecialchars((string) $categoria->id_categoria, ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                                            <?php if ((int) $categoria->estado === 1): ?>
                                                <form method="post" action="/proyectofinaldesarrollo/categoria/baja/<?= htmlspecialchars((string) $categoria->id_categoria, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit">Dar de baja</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="/proyectofinaldesarrollo/categoria/reactivar/<?= htmlspecialchars((string) $categoria->id_categoria, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="btn btn-outline-success btn-sm" type="submit">Reactivar</button>
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
