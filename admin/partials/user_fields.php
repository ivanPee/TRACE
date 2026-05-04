<?php
$record = $record ?? [];
$selectedRole = $record['role_code'] ?? 'parent';
?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">First name</label>
        <input class="form-control" name="first_name" value="<?= e($record['first_name'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Middle name</label>
        <input class="form-control" name="middle_name" value="<?= e($record['middle_name'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Last name</label>
        <input class="form-control" name="last_name" value="<?= e($record['last_name'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Email</label>
        <input class="form-control" type="email" name="email" value="<?= e($record['email'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Mobile number</label>
        <input class="form-control" name="mobile_number" value="<?= e($record['mobile_number'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Role</label>
        <select class="form-select" name="role_code" required>
            <?php foreach ($roles as $role): ?>
                <option value="<?= e($role['code']) ?>" <?= $selectedRole === $role['code'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Status</label>
        <select class="form-select" name="status">
            <?php foreach (['pending', 'active', 'suspended', 'rejected'] as $status): ?>
                <option value="<?= e($status) ?>" <?= ($record['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Password <?= !empty($record) ? '<span class="text-secondary fw-normal">(optional)</span>' : '' ?></label>
        <input class="form-control" type="password" name="password" <?= empty($record) ? 'required' : '' ?>>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_verified" value="1" id="verified<?= e($record['id'] ?? 'new') ?>" <?= !empty($record['is_verified']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="verified<?= e($record['id'] ?? 'new') ?>">Verified account</label>
        </div>
    </div>
</div>

