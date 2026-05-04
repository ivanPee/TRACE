<div class="row g-3">
    <div class="col-md-6"><label class="form-label fw-semibold">Driver</label><select class="form-select" name="driver_id" required><?php foreach ($drivers as $driver): ?><option value="<?= (int) $driver['id'] ?>" <?= (int) ($record['driver_id'] ?? 0) === (int) $driver['id'] ? 'selected' : '' ?>><?= e(trim($driver['first_name'] . ' ' . $driver['last_name'])) ?> (<?= e($driver['vehicle_plate_number']) ?>)</option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Plate number</label><input class="form-control" name="plate_number" value="<?= e($record['plate_number'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Model</label><input class="form-control" name="model" value="<?= e($record['model'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Color</label><input class="form-control" name="color" value="<?= e($record['color'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Capacity</label><input class="form-control" type="number" min="1" name="capacity" value="<?= e($record['capacity'] ?? 1) ?>" required></div>
    <div class="col-md-8"><label class="form-label fw-semibold">Registration path</label><input class="form-control" name="registration_path" value="<?= e($record['registration_path'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Status</label><select class="form-select" name="status"><?php foreach (['active', 'inactive', 'maintenance'] as $status): ?><option value="<?= e($status) ?>" <?= ($record['status'] ?? 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
</div>

