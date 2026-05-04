<?php
$recordId = array_get($record, 'id', 'new');
$studentStatus = array_get($record, 'status', array_get($record, 'student_status', 'active'));
?>
<div class="row g-3">
    <div class="col-md-4"><label class="form-label fw-semibold">First name</label><input class="form-control" name="first_name" value="<?= e(array_get($record, 'first_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Middle name</label><input class="form-control" name="middle_name" value="<?= e(array_get($record, 'middle_name')) ?>"></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Last name</label><input class="form-control" name="last_name" value="<?= e(array_get($record, 'last_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Email</label><input class="form-control" type="email" name="email" value="<?= e(array_get($record, 'email')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Mobile</label><input class="form-control" name="mobile_number" value="<?= e(array_get($record, 'mobile_number')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Password <?= !empty($record['id']) ? '<span class="text-secondary fw-normal">(optional)</span>' : '' ?></label><input class="form-control" type="password" name="password" <?= empty($record['id']) ? 'required' : '' ?>></div>
    <input type="hidden" name="role_code" value="student">
    <div class="col-md-4"><label class="form-label fw-semibold">Parent</label><select class="form-select" name="parent_id" required><?php foreach ($parents as $parent): ?><option value="<?= (int) $parent['id'] ?>" <?= (int) array_get($record, 'parent_id', 0) === (int) $parent['id'] ? 'selected' : '' ?>><?= e(trim($parent['first_name'] . ' ' . $parent['last_name'])) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label fw-semibold">User status</label><select class="form-select" name="user_status"><?php foreach (array('pending', 'active', 'suspended', 'rejected') as $status): ?><option value="<?= e($status) ?>" <?= array_get($record, 'user_status', 'active') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Student status</label><select class="form-select" name="student_status"><?php foreach (array('active', 'inactive') as $status): ?><option value="<?= e($status) ?>" <?= $studentStatus === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label fw-semibold">LRN</label><input class="form-control" name="lrn" value="<?= e(array_get($record, 'lrn')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">School</label><input class="form-control" name="school_name" value="<?= e(array_get($record, 'school_name')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Grade level</label><input class="form-control" name="grade_level" value="<?= e(array_get($record, 'grade_level')) ?>" required></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Pickup address</label><textarea class="form-control" name="pickup_address" rows="2" required><?= e(array_get($record, 'pickup_address')) ?></textarea></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Dropoff address</label><textarea class="form-control" name="dropoff_address" rows="2" required><?= e(array_get($record, 'dropoff_address')) ?></textarea></div>
    <div class="col-12"><label class="form-label fw-semibold">Medical notes</label><textarea class="form-control" name="medical_notes" rows="2"><?= e(array_get($record, 'medical_notes')) ?></textarea></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_verified" value="1" id="sv<?= e($recordId) ?>" <?= !empty($record['is_verified']) ? 'checked' : '' ?>><label class="form-check-label" for="sv<?= e($recordId) ?>">Verified account</label></div></div>
</div>
