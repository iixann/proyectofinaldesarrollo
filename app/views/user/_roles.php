<?php // Comparte la selección exclusiva en altas y cambios de roles. ?>
<fieldset class="mb-4" data-roles-exclusivos>
    <legend class="fs-6 fw-semibold">Roles (seleccioná al menos uno)</legend>
    <p class="small text-secondary">Administrador y Dueño se asignan solos. Vendedor y Recepcionista pueden combinarse.</p>
    <div class="row g-3">
        <?php foreach ($roles as $rol): ?>
            <div class="col-12 col-sm-6">
                <label class="d-flex align-items-center gap-3 border rounded-3 p-3 h-100">
                    <input class="form-check-input mt-0 flex-shrink-0" type="checkbox" name="roles[]" data-rol="<?= htmlspecialchars($rol->nombre_rol, ENT_QUOTES, 'UTF-8') ?>" value="<?= (int) $rol->id_rol ?>" <?= in_array($rol->id_rol, $seleccionados) ? 'checked' : '' ?>>
                    <span class="small fw-semibold"><?= htmlspecialchars($rol->nombre_rol, ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-danger small mt-2" data-error-roles role="alert" hidden>Seleccioná al menos un rol.</p>
    <?php if (!$roles): ?><p class="alert alert-warning mt-3 mb-0">No hay roles activos disponibles.</p><?php endif; ?>
</fieldset>
