<!DOCTYPE html>
<html lang="es">
<head>
    <title>Restablecer contraseña | Identidad Singular</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'usuarios'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Restablecer contraseña</h1>
        <p>Usuario: <strong><?= htmlspecialchars($usuario->nombre . ' ' . $usuario->apellido, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($usuario->email, ENT_QUOTES, 'UTF-8') ?>)</p>
        <p class="text-secondary">La contraseña temporal deberá cambiarse al próximo ingreso. Esto no modifica el estado ni los roles.</p>
        <section class="card border-0 shadow-sm rounded-4 col-lg-7"><div class="card-body p-4">
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <form method="post" action="/proyectofinaldesarrollo/user/guardarPassword/<?= (int) $usuario->id_usuario ?>">
                <input type="hidden" name="csrf_password" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="mb-3">
                    <label class="form-label" for="contrasena">Nueva contraseña temporal</label>
                    <input class="form-control" id="contrasena" name="contrasena" type="password" minlength="9" autocomplete="new-password" required>
                    <div class="form-text">Mínimo 9 caracteres y hasta 72 bytes.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="confirmacion">Confirmar nueva contraseña</label>
                    <input class="form-control" id="confirmacion" name="confirmacion" type="password" minlength="9" autocomplete="new-password" required>
                </div>
                <button class="btn btn-primary" type="submit">Restablecer contraseña</button>
                <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/user/index">Cancelar</a>
            </form>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
