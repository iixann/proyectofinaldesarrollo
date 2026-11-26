<!DOCTYPE html>
<html lang="es">
<head>
    <title>Cambiar roles | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'usuarios'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <a href="/proyectofinaldesarrollo/user/index">Volver a Usuarios</a>
        <h1 class="h2 fw-bold my-4">Cambiar roles</h1>
        <div class="card border-0 shadow-sm col-lg-9 col-xl-8"><div class="card-body p-4">
            <p><strong>Usuario:</strong> <?= htmlspecialchars($usuario->nombre . ' ' . $usuario->apellido, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-secondary"><?= htmlspecialchars($usuario->email, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($errores): ?><div class="alert alert-danger" role="alert"><ul class="mb-0">
                <?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul></div><?php endif; ?>
            <!-- El servidor comprueba permiso, token y combinación antes de guardar. -->
            <form method="post" action="/proyectofinaldesarrollo/user/guardarRoles/<?= (int) $usuario->id_usuario ?>">
                <input type="hidden" name="csrf_roles" value="<?= htmlspecialchars($tokenRoles, ENT_QUOTES, 'UTF-8') ?>">
                <?php require APP_PATH . 'views/user/_roles.php'; ?>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit" <?= !$roles ? 'disabled' : '' ?>>Guardar roles</button>
                    <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/user/index">Cancelar</a>
                </div>
            </form>
        </div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
    <script src="/proyectofinaldesarrollo/js/usuarios.js?v=<?= substr(hash_file('sha256','public/js/usuarios.js'),0,12) ?>" defer></script>
</body>
</html>
