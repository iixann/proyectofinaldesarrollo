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
            <p class="text-secondary mb-3">Panel principal</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="small text-secondary">Roles actuales:</span>
                <?php foreach ($usuario['roles'] as $rol): ?>
                    <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis px-3 py-2"><?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <section aria-labelledby="accesosRapidos">
            <h2 class="h5 fw-semibold mb-4" id="accesosRapidos">Accesos rápidos</h2>
            <?php $modulos = [
                ['nombre' => 'Usuarios', 'texto' => 'Usuarios y roles', 'icono' => 'fa-users', 'ruta' => 'user/index', 'soloAdministrador' => true],
                ['nombre' => 'Categorías', 'texto' => 'Clasificación del catálogo', 'icono' => 'fa-tags', 'ruta' => 'categoria/index'],
                ['nombre' => 'Proveedores', 'texto' => 'Datos comerciales y contacto', 'icono' => 'fa-truck', 'ruta' => 'proveedor/index'],
                ['nombre' => 'Productos', 'texto' => 'EAN, precios y proveedores', 'icono' => 'fa-wine-bottle', 'ruta' => 'producto/index'],
                ['nombre' => 'Stock', 'texto' => 'Existencias, ajustes y movimientos', 'icono' => 'fa-boxes-stacked', 'ruta' => 'stock/index'],
                ['nombre' => 'Reposición', 'texto' => 'Productos con stock bajo', 'icono' => 'fa-list-check', 'ruta' => 'reposicion/index'],
                ['nombre' => 'Compras', 'texto' => 'Pedidos al proveedor', 'icono' => 'fa-cart-shopping', 'ruta' => 'compra/index'],
                ['nombre' => 'Recepción', 'texto' => 'Ingreso físico de mercadería e histórico', 'icono' => 'fa-truck-ramp-box', 'ruta' => 'recepcion/index'],
                ['nombre' => 'Cajas', 'texto' => 'Administración de terminales', 'icono' => 'fa-cash-register', 'ruta' => 'caja/index'],
                ['nombre' => 'Sesiones de caja', 'texto' => 'Apertura, cierre e histórico', 'icono' => 'fa-door-open', 'ruta' => 'sesioncaja/index'],
                ['nombre' => 'Punto de Venta', 'texto' => 'Carrito, cobro y confirmación', 'icono' => 'fa-basket-shopping', 'ruta' => 'pos/index'],
                ['nombre' => 'Ventas', 'texto' => 'Histórico, detalle y tickets', 'icono' => 'fa-receipt', 'ruta' => 'venta/index']
            ]; ?>
            <div class="row g-4">
                <?php foreach ($modulos as $modulo): ?>
                    <?php if (!empty($modulo['soloAdministrador']) && !\app\controllers\SessionController::esAdministrador()) { continue; } ?>
                    <div class="col-12 col-md-6 col-lg-4"><article class="card tarjeta-modulo h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4 d-flex flex-column align-items-start">
                            <span class="icono-modulo rounded-3 bg-primary-subtle text-primary mb-3"><i class="fa-solid <?= htmlspecialchars($modulo['icono'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                            <h3 class="h5"><?= htmlspecialchars($modulo['nombre'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="text-secondary"><?= htmlspecialchars($modulo['texto'], ENT_QUOTES, 'UTF-8') ?></p>
                            <a class="btn btn-primary mt-auto" href="/proyectofinaldesarrollo/<?= htmlspecialchars($modulo['ruta'], ENT_QUOTES, 'UTF-8') ?>">Abrir <?= htmlspecialchars($modulo['nombre'], ENT_QUOTES, 'UTF-8') ?></a>
                        </div>
                    </article></div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php require APP_PATH . "views/inc/footer.php"; ?>
</body>
</html>
