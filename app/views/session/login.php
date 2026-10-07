<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Ingresar</title></head>
<body>
    <h1>Ingresar</h1>
    <?php if ($mensaje !== ''): ?>
        <p><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (\app\controllers\SessionController::sesionValida()): ?>
        <p>Usuario conectado: <?= htmlspecialchars($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido'], ENT_QUOTES, 'UTF-8') ?></p>
        <a href="/proyectofinaldesarrollo/session/logout">Cerrar sesión</a>
    <?php else: ?>
        <form method="post" action="/proyectofinaldesarrollo/session/ingresar">
            <p><label>Correo electrónico <input type="email" name="correo" value="<?= htmlspecialchars($correo, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required></label></p>
            <p><label>Contraseña <input type="password" name="contrasena" autocomplete="current-password" required></label></p>
            <button type="submit">Ingresar</button>
        </form>
    <?php endif; ?>
</body>
</html>
