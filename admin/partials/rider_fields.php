<?php $recordId = array_get($record, 'id', 'new'); ?>
<div class="row g-3">
    <div class="col-12"><h4 class="h6 text-secondary mb-0">Account</h4></div>
    <div class="col-md-4"><label class="form-label fw-semibold">First name</label><input class="form-control" name="first_name" value="<?= e(array_get($record, 'first_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Middle name</label><input class="form-control" name="middle_name" value="<?= e(array_get($record, 'middle_name')) ?>"></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Last name</label><input class="form-control" name="last_name" value="<?= e(array_get($record, 'last_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Email</label><input class="form-control" type="email" name="email" value="<?= e(array_get($record, 'email')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Mobile</label><input class="form-control" name="mobile_number" value="<?= e(array_get($record, 'mobile_number')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Password <?= !empty($record['id']) ? '<span class="text-secondary fw-normal">(optional)</span>' : '' ?></label><input class="form-control" type="password" name="password" <?= empty($record['id']) ? 'required' : '' ?>></div>
    <input type="hidden" name="role_code" value="driver">
    <div class="col-md-4"><label class="form-label fw-semibold">User status</label><select class="form-select" name="status"><?php foreach (array('pending', 'active', 'suspended', 'rejected') as $status): ?><option value="<?= e($status) ?>" <?= array_get($record, 'status', 'pending') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Approval</label><select class="form-select" name="approval_status"><?php foreach (array('pending', 'approved', 'rejected') as $status): ?><option value="<?= e($status) ?>" <?= array_get($record, 'approval_status', 'pending') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4 d-flex align-items-end gap-4">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_verified" value="1" id="rv<?= e($recordId) ?>" <?= !empty($record['is_verified']) ? 'checked' : '' ?>><label class="form-check-label" for="rv<?= e($recordId) ?>">Verified</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_online" value="1" id="ro<?= e($recordId) ?>" <?= !empty($record['is_online']) ? 'checked' : '' ?>><label class="form-check-label" for="ro<?= e($recordId) ?>">Online</label></div>
    </div>
    <div class="col-12"><hr><h4 class="h6 text-secondary mb-0">License and Vehicle</h4></div>
    <div class="col-md-4"><label class="form-label fw-semibold">License number</label><input class="form-control" name="license_number" value="<?= e(array_get($record, 'license_number')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">License expiry</label><input class="form-control" type="date" name="license_expiry" value="<?= e(array_get($record, 'license_expiry')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">License photo path</label><input class="form-control" name="license_photo_path" value="<?= e(array_get($record, 'license_photo_path', 'pending-upload')) ?>" required></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Vehicle type</label><input class="form-control" name="vehicle_type" value="<?= e(array_get($record, 'vehicle_type')) ?>" required></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Plate number</label><input class="form-control" name="vehicle_plate_number" value="<?= e(array_get($record, 'vehicle_plate_number')) ?>" required></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Model</label><input class="form-control" name="vehicle_model" value="<?= e(array_get($record, 'vehicle_model')) ?>" required></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Color</label><input class="form-control" name="vehicle_color" value="<?= e(array_get($record, 'vehicle_color')) ?>" required></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Vehicle photo path</label><input class="form-control" name="vehicle_photo_path" value="<?= e(array_get($record, 'vehicle_photo_path')) ?>"></div>
    <div class="col-md-6"><label class="form-label fw-semibold">ORCR path</label><input class="form-control" name="registration_path" value="<?= e(array_get($record, 'registration_path')) ?>"></div>
    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="license_verified" value="1" id="rl<?= e($recordId) ?>" <?= !empty($record['license_verified']) ? 'checked' : '' ?>><label class="form-check-label" for="rl<?= e($recordId) ?>">License verified</label></div></div>
</div>
