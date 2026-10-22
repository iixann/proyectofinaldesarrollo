<!DOCTYPE html>
<html lang="es">
<head>
    <title>Editar proveedor | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'proveedores'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Editar proveedor</h1>
        <div class="row"><div class="col-12 col-lg-8">
            <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 p-md-5">
                <?php if ($errores): ?>
                    <div class="alert alert-danger" role="alert"><ul class="mb-0">
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>
                <form method="post" action="/proyectofinaldesarrollo/proveedor/actualizar/<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="razon_social">Razón social *</label>
                            <input class="form-control" id="razon_social" name="razon_social" type="text" maxlength="150" value="<?= htmlspecialchars($datosProveedor['razon_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="cuit">CUIT</label>
                            <input class="form-control" id="cuit" name="cuit" type="text" maxlength="13" value="<?= htmlspecialchars($datosProveedor['cuit'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="nombre_contacto">Nombre de contacto</label>
                            <input class="form-control" id="nombre_contacto" name="nombre_contacto" type="text" maxlength="100" value="<?= htmlspecialchars($datosProveedor['nombre_contacto'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input class="form-control" id="telefono" name="telefono" type="text" maxlength="50" value="<?= htmlspecialchars($datosProveedor['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" maxlength="150" value="<?= htmlspecialchars($datosProveedor['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="direccion">Dirección</label>
                            <input class="form-control" id="direccion" name="direccion" type="text" maxlength="200" value="<?= htmlspecialchars($datosProveedor['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 border-top pt-4">
                        <button class="btn btn-primary" type="submit">Guardar cambios</button>
                        <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/proveedor/index">Cancelar</a>
                    </div>
                </form>
            </div></section>
        </div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
