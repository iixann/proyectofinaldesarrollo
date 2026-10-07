<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Panel de administración</title></head>
<body>
    <h1>Panel de administración</h1>
    <p>Usuario conectado: <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido'], ENT_QUOTES, 'UTF-8') ?></p>
    <p>Rol/es: <?= htmlspecialchars(implode(', ', $usuario['roles']), ENT_QUOTES, 'UTF-8') ?></p>
    <ul>
        <li><a href="/proyectofinaldesarrollo/user/index">Listado de usuarios</a></li>
        <li><a href="/proyectofinaldesarrollo/user/crear">Crear usuario</a></li>
        <li><a href="/proyectofinaldesarrollo/session/logout">Cerrar sesión</a></li>
    </ul>
</body>
</html>
