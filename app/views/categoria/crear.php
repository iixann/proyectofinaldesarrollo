<!DOCTYPE html>
<html lang="es">
<head>
    <title>Nueva categoría | Sistema de Gestión de Vinería</title>
    <?php require APP_PATH . 'views/inc/head.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php $seccionActual = 'categorias'; require APP_PATH . 'views/inc/navbar.php'; ?>
    <main class="container py-4 py-md-5 flex-grow-1" id="contenido">
        <h1 class="h2 fw-bold mb-4">Nueva categoría</h1>
        <div class="row"><div class="col-12 col-lg-8">
            <section class="card border-0 shadow-sm rounded-4"><div class="card-body p-4 p-md-5">
                <?php if ($errores): ?>
                    <div class="alert alert-danger" role="alert"><ul class="mb-0">
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>
                <form method="post" action="/proyectofinaldesarrollo/categoria/guardar">
                    <div class="mb-4">
                        <label class="form-label" for="nombre_categoria">Nombre de categoría</label>
                        <input class="form-control" id="nombre_categoria" name="nombre_categoria" type="text" maxlength="100" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="d-flex flex-wrap gap-2 border-top pt-4">
                        <button class="btn btn-primary" type="submit">Guardar categoría</button>
                        <a class="btn btn-outline-secondary" href="/proyectofinaldesarrollo/categoria/index">Cancelar</a>
                    </div>
                </form>
            </div></section>
        </div></div>
    </main>
    <?php require APP_PATH . 'views/inc/footer.php'; ?>
</body>
</html>
