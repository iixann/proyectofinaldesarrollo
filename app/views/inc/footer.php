    <footer class="container py-4 text-secondary small">
        <div class="border-top pt-3">Identidad Singular · Sistema de Gestión de Vinería</div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (\app\controllers\SessionController::sesionValida()
        && empty($_SESSION['usuario']['debe_cambiar_password'])
        && array_intersect(['ADMINISTRADOR', 'DUEÑO', 'VENDEDOR', 'RECEPCIONISTA'], $_SESSION['usuario']['roles'])): ?>
    <?php require APP_PATH . 'views/inc/consulta_rapida.php'; ?>
    <script src="/proyectofinaldesarrollo/js/atajos.js?v=<?= substr(hash_file('sha256', APP_PATH . '../public/js/atajos.js'), 0, 12) ?>" defer></script>
    <?php endif; ?>
