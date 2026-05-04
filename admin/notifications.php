<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');
        $referenceId = post_value('reference_id') !== '' ? (int) post_value('reference_id') : null;
        $params = [(int) post_value('user_id'), post_value('title'), post_value('body'), post_value('type'), $referenceId, !empty($_POST['is_read']) ? 1 : 0];

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO notifications (user_id, title, body, type, reference_id, is_read) VALUES (?, ?, ?, ?, ?, ?)')->execute($params);
            flash('success', 'Alert created successfully.');
            redirect_to('notifications.php');
        }

        if ($action === 'update') {
            $params[] = (int) post_value('notification_id');
            $pdo->prepare('UPDATE notifications SET user_id = ?, title = ?, body = ?, type = ?, reference_id = ?, is_read = ? WHERE id = ?')->execute($params);
            flash('success', 'Alert updated successfully.');
            redirect_to('notifications.php');
        }

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM notifications WHERE id = ?')->execute([(int) post_value('notification_id')]);
            flash('success', 'Alert deleted successfully.');
            redirect_to('notifications.php');
        }
    }
} catch (Exception $exception) {
    flash('error', $exception->getMessage());
    redirect_to('notifications.php');
}

$users = $pdo->query('SELECT id, first_name, last_name, email FROM users ORDER BY last_name, first_name')->fetchAll();
$notifications = $pdo->query(
    'SELECT notifications.*, users.first_name, users.last_name, users.email
     FROM notifications
     JOIN users ON users.id = notifications.user_id
     ORDER BY notifications.created_at DESC, notifications.id DESC'
)->fetchAll();

admin_header('Alerts', 'notifications', 'Manage user notifications and operational alerts.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Alerts</h2><span class="text-secondary small"><?= count($notifications) ?> total records</span></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createAlertModal" <?= !$users ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Add alert</button>
    </div>
    <div class="card-body">
        <?php if (!$users): ?><div class="alert alert-warning">Create at least one user before adding alerts.</div><?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>User</th><th>Title</th><th>Type</th><th>Body</th><th>Read</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td><?= e(trim($notification['first_name'] . ' ' . $notification['last_name'])) ?><div class="text-secondary small"><?= e($notification['email']) ?></div></td>
                            <td class="fw-semibold"><?= e($notification['title']) ?></td>
                            <td><span class="badge text-bg-primary"><?= e($notification['type']) ?></span></td>
                            <td class="text-secondary"><?= e(text_excerpt((string) $notification['body'])) ?></td>
                            <td><?= (int) $notification['is_read'] === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning">No</span>' ?></td>
                            <td><?= e($notification['created_at']) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editAlert<?= (int) $notification['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this alert?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$notifications): ?><tr><td colspan="7" class="text-center text-secondary py-4">No alerts yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $record = ['type' => 'system']; ?>
<div class="modal fade" id="createAlertModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="modal-header"><h3 class="modal-title h5">Add Alert</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><?php include __DIR__ . '/partials/notification_fields.php'; ?></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save alert</button></div>
</form></div></div>

<?php foreach ($notifications as $record): ?>
    <div class="modal fade" id="editAlert<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="notification_id" value="<?= (int) $record['id'] ?>">
        <div class="modal-header"><h3 class="modal-title h5">Edit Alert</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><?php include __DIR__ . '/partials/notification_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update alert</button></div>
    </form></div></div>
<?php endforeach; ?>

<?php admin_footer(); ?>
