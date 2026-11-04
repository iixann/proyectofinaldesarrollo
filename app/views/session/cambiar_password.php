<!DOCTYPE html>
<html lang="es">
<head>
    <title>Cambiar contraseña | Identidad Singular</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">

    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Cambiar contraseña</h1>
        <p class="alert alert-warning">Debés cambiar tu contraseña antes de continuar.</p>
        <section class="card border-0 shadow-sm rounded-4 col-lg-7"><div class="card-body p-4">
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/session/guardarPassword">
                <input type="hidden" name="csrf_password" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label" for="contrasena">Nueva contraseña *</label>
                    <input class="form-control" id="contrasena" name="contrasena" type="password" minlength="9" autocomplete="new-password" required>
                    <div class="form-text">Mínimo 9 caracteres y hasta 72 bytes.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="confirmacion">Confirmar nueva contraseña *</label>
                    <input class="form-control" id="confirmacion" name="confirmacion" type="password" minlength="9" autocomplete="new-password" required>
                </div>
                <div class="acciones-formulario d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Guardar nueva contraseña</button>
                <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/session/logout">Cerrar sesión</a>
                </div>
            </form>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
