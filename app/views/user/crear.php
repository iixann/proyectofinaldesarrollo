<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Crear usuario</title></head>
<body>
    <h1>Crear usuario</h1>
    <p><a href="/proyectofinaldesarrollo/admin/index">Volver al panel</a></p>
    <p><a href="/proyectofinaldesarrollo/user/index">Listado de usuarios</a></p>
    <?php if ($errores): ?>
        <ul>
            <?php foreach ($errores as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="/proyectofinaldesarrollo/user/guardar">
        <p><label>Nombre <input type="text" name="nombre" value="<?= htmlspecialchars($datosUsuario['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
        <p><label>Apellido <input type="text" name="apellido" value="<?= htmlspecialchars($datosUsuario['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
        <p><label>Email <input type="email" name="correo" value="<?= htmlspecialchars($datosUsuario['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label></p>
        <p><label>Contraseña <input type="password" name="contrasena" autocomplete="new-password" required></label></p>
        <fieldset>
            <legend>Roles (seleccioná al menos uno)</legend>
            <?php foreach ($roles as $rol): ?>
                <p><label><input type="checkbox" name="roles[]" value="<?= htmlspecialchars((string) $rol->id_rol, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($rol->id_rol, $datosUsuario['roles'] ?? []) ? 'checked' : '' ?>> <?= htmlspecialchars($rol->nombre_rol, ENT_QUOTES, 'UTF-8') ?></label></p>
            <?php endforeach; ?>
            <?php if (!$roles): ?><p>No hay roles activos disponibles.</p><?php endif; ?>
        </fieldset>
        <button type="submit" <?= !$roles ? 'disabled' : '' ?>>Crear usuario</button>
    </form>
</body>
</html>
