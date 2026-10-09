<!DOCTYPE html>
<html lang="es">
<head>
    <title>Inicio | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . "views/inc/head.php"; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'inicio'; require APP_PATH . "views/inc/navbar.php"; ?>

    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <section class="mb-5" aria-labelledby="bienvenida">
            <p class="text-primary fw-semibold small mb-2"><i class="fa-solid fa-house me-2" aria-hidden="true"></i>INICIO</p>
            <h1 class="h2 fw-bold mb-2" id="bienvenida">Bienvenido, <?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-secondary mb-3">Panel de administración</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small text-secondary">Roles actuales:</span>
                <?php foreach ($usuario['roles'] as $rol): ?>
                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis px-3 py-2"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <section aria-labelledby="accesosRapidos">
            <div class="mb-4">
                <h2 class="h5 fw-semibold mb-2" id="accesosRapidos">Accesos rápidos</h2>
                <p class="text-secondary small mb-0">Accedé a la gestión del sistema. Los módulos pendientes se indican como próximamente.</p>
            </div>
            <div class="row g-4">
                <div class="col-12 col-md-6 col-lg-4">
                    <article class="card tarjeta-modulo h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4 d-flex flex-column align-items-start">
                            <span class="icono-modulo rounded-3 bg-primary-subtle text-primary mb-4"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                            <h3 class="h5 fw-semibold">Usuarios</h3>
                            <p class="text-secondary mb-4">Gestionar usuarios y roles</p>
                            <a class="btn btn-primary mt-auto" href="/proyectofinaldesarrollo/user/index">Gestionar usuarios<i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></a>
                        </div>
                    </article>
                </div>
                <?php
                $modulosPendientes = [
                    ['nombre' => 'Productos', 'texto' => 'Catálogo e inventario', 'icono' => 'fa-wine-bottle'],
                    ['nombre' => 'Proveedores', 'texto' => 'Gestión de proveedores', 'icono' => 'fa-truck'],
                    ['nombre' => 'Compras', 'texto' => 'Ingreso de mercadería', 'icono' => 'fa-cart-shopping'],
                    ['nombre' => 'Stock', 'texto' => 'Control de existencias', 'icono' => 'fa-boxes-stacked'],
                    ['nombre' => 'Ventas', 'texto' => 'Punto de venta', 'icono' => 'fa-cash-register']
                ];
                ?>
                <?php foreach ($modulosPendientes as $modulo): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <article class="card tarjeta-modulo h-100 border-0 shadow-sm rounded-4">
                            <div class="card-body p-4 d-flex flex-column align-items-start">
                                <span class="icono-modulo rounded-3 bg-light text-secondary mb-4"><i class="fa-solid <?= htmlspecialchars($modulo['icono'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                                <h3 class="h5 fw-semibold"><?= htmlspecialchars($modulo['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="text-secondary mb-4"><?= htmlspecialchars($modulo['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="badge rounded-pill bg-light text-secondary border px-3 py-2 mt-auto">Próximamente</span>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php require APP_PATH . "views/inc/footer.php"; ?>
</body>
</html>
