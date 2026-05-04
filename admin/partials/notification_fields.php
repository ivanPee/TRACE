<?php $recordId = array_get($record, 'id', 'new'); ?>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label fw-semibold">User</label><select class="form-select" name="user_id" required><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>" <?= (int) array_get($record, 'user_id', 0) === (int) $user['id'] ? 'selected' : '' ?>><?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?> - <?= e($user['email']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Type</label><input class="form-control" name="type" value="<?= e(array_get($record, 'type', 'system')) ?>" required></div>
    <div class="col-md-8"><label class="form-label fw-semibold">Title</label><input class="form-control" name="title" value="<?= e(array_get($record, 'title')) ?>" required></div>
    <div class="col-md-4"><label class="form-label fw-semibold">Reference ID</label><input class="form-control" type="number" min="1" name="reference_id" value="<?= e(array_get($record, 'reference_id')) ?>"></div>
    <div class="col-12"><label class="form-label fw-semibold">Body</label><textarea class="form-control" name="body" rows="4" required><?= e(array_get($record, 'body')) ?></textarea></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_read" value="1" id="nr<?= e($recordId) ?>" <?= !empty($record['is_read']) ? 'checked' : '' ?>><label class="form-check-label" for="nr<?= e($recordId) ?>">Read</label></div></div>
</div>
