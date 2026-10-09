<!DOCTYPE html>
<html lang="es">
<head>
    <title>Crear usuario | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'usuarios'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap gap-3 mb-4">
            <a class="link-primary text-decoration-none small" href="/proyectofinaldesarrollo/user/index"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Volver al listado de usuarios</a>
            <a class="link-secondary text-decoration-none small" href="/proyectofinaldesarrollo/admin/index">Volver al panel principal</a>
        </div>
        <h1 class="h2 fw-bold mb-2">Crear usuario</h1>
        <p class="text-secondary mb-4">Completá los datos y seleccioná los roles del usuario.</p>
        <div class="row">
            <div class="col-12 col-lg-9 col-xl-8">
                <section class="card border-0 shadow-sm rounded-4" aria-label="Formulario de creación de usuario">
                    <div class="card-body p-4 p-md-5">
                        <?php if ($errores): ?>
                            <div class="alert alert-danger" role="alert">
                                <p class="fw-semibold mb-2">No se pudo guardar el usuario.</p>
                                <ul class="mb-0">
                                    <?php foreach ($errores as $error): ?>
                                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="/proyectofinaldesarrollo/user/guardar">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label" for="nombre">Nombre</label>
                                    <input class="form-control" id="nombre" type="text" name="nombre" value="<?= htmlspecialchars($datosUsuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="apellido">Apellido</label>
                                    <input class="form-control" id="apellido" type="text" name="apellido" value="<?= htmlspecialchars($datosUsuario['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="correo">Email</label>
                                    <input class="form-control" id="correo" type="email" name="correo" value="<?= htmlspecialchars($datosUsuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="contrasena">Contraseña</label>
                                    <input class="form-control" id="contrasena" type="password" name="contrasena" autocomplete="new-password" required>
                                </div>
                            </div>
                            <fieldset class="mb-4">
                                <legend class="fs-6 fw-semibold">Roles (seleccioná al menos uno)</legend>
                                <div class="row g-3">
                                    <?php foreach ($roles as $rol): ?>
                                        <div class="col-12 col-sm-6">
                                            <label class="d-flex align-items-center gap-3 border rounded-3 p-3 h-100">
                                                <input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="roles[]" value="<?= htmlspecialchars((string) $rol->id_rol, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($rol->id_rol, $datosUsuario['roles'] ?? []) ? 'checked' : '' ?>>
                                                <span class="small fw-semibold"><?= htmlspecialchars($rol->nombre_rol, ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (!$roles): ?><p class="alert alert-warning mt-3 mb-0">No hay roles activos disponibles.</p><?php endif; ?>
                            </fieldset>
                            <div class="d-flex flex-wrap gap-2 border-top pt-4">
                                <button class="btn btn-primary" type="submit" <?= !$roles ? 'disabled' : '' ?>><i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>Guardar usuario</button>
                                <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/user/index">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
