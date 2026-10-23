<!DOCTYPE html>
<html lang="es">
<head>
    <title>Sesiones de caja | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'sesiones'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><h1 class="h2 fw-bold">Sesiones de caja</h1><p class="text-secondary mb-0">Aperturas y cierres de los turnos de caja.</p></div>
            <a class="btn btn-primary" href="/proyectofinaldesarrollo/sesioncaja/abrir">Abrir caja</a>
        </div>
        <?php if ($mensaje !== ''): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-3 p-md-4">
            <?php if (!$sesiones): ?><p class="text-secondary text-center my-4">No hay sesiones de caja registradas.</p><?php else: ?>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">ID sesión</th><th scope="col">Caja</th><th scope="col">Usuario apertura</th><th scope="col">Fecha apertura</th><th scope="col">Monto inicial</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($sesiones as $sesion): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $sesion->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($sesion->caja, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($sesion->nombre . ' ' . $sesion->apellido, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($sesion->fecha_apertura, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>$ <?= htmlspecialchars((string) $sesion->monto_inicial, ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $sesion->estado === 'ABIERTA' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= htmlspecialchars($sesion->estado, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><div class="d-flex gap-2">
                                    <a class="btn btn-outline-secondary btn-sm" href="/proyectofinaldesarrollo/sesioncaja/ver/<?= htmlspecialchars((string) $sesion->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                                    <?php if ($sesion->estado === 'ABIERTA'): ?>
                                        <a class="btn btn-outline-primary btn-sm" href="/proyectofinaldesarrollo/sesioncaja/cerrar/<?= htmlspecialchars((string) $sesion->id_caja_sesion, ENT_QUOTES, 'UTF-8') ?>">Cerrar caja</a>
                                    <?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div></section>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
