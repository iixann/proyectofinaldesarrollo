<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Usuarios</title></head>
<body>
    <h1>Usuarios</h1>
    <p><a href="/proyectofinaldesarrollo/admin/index">Volver al panel</a></p>
    <p><a href="/proyectofinaldesarrollo/user/crear">Crear usuario</a></p>
    <?php if (empty($usuarios)): ?>
        <p>No hay usuarios registrados.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>Email</th><th>Estado</th><th>Roles</th></tr></thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $usuario->id_usuario, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($usuario->nombre, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($usuario->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($usuario->email, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $usuario->estado === 1 ? 'Activo' : 'Inactivo' ?></td>
                        <td><?= htmlspecialchars(implode(', ', array_column($usuario->roles, 'nombre_rol')), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
