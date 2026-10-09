<!DOCTYPE html>
<html lang="es">
<head>
    <title>Iniciar sesión | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <main class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
        <div class="row justify-content-center w-100">
            <div class="col-12 col-md-8 col-lg-5 col-xl-4 px-0">
                <section class="card border-0 shadow-sm rounded-4" aria-labelledby="tituloLogin">
                    <div class="card-body p-4 p-sm-5">
                        <div class="text-center mb-4">
                            <span class="icono-modulo rounded-3 bg-primary-subtle text-primary mb-3"><i class="fa-solid fa-wine-glass" aria-hidden="true"></i></span>
                            <h1 class="h4 fw-bold" id="tituloLogin">Sistema de Gestión de Vinería</h1>
                            <p class="text-secondary mb-0">Iniciar sesión</p>
                        </div>
                        <?php if ($mensaje !== ''): ?>
                            <div class="alert alert-warning" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if (\app\controllers\SessionController::sesionValida()): ?>
                            <p class="text-break">Usuario conectado: <strong><?= htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                            <a class="btn btn-outline-primary w-100" href="/proyectofinaldesarrollo/session/logout"><i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>Cerrar sesión</a>
                        <?php else: ?>
                            <form method="post" action="/proyectofinaldesarrollo/session/ingresar">
                                <div class="mb-3">
                                    <label class="form-label" for="correo">Correo electrónico</label>
                                    <input class="form-control" id="correo" type="email" name="correo" value="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="contrasena">Contraseña</label>
                                    <input class="form-control" id="contrasena" type="password" name="contrasena" autocomplete="current-password" required>
                                </div>
                                <button class="btn btn-primary w-100 py-2" type="submit"><i class="fa-solid fa-right-to-bracket me-2" aria-hidden="true"></i>Ingresar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
