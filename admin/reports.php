<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        if (post_value('action') === 'delete') {
            $pdo->prepare('DELETE FROM admin_logs WHERE id = ?')->execute([(int) post_value('log_id')]);
            flash('success', 'Admin log deleted successfully.');
            redirect_to('reports.php');
        }
    }
} catch (Exception $exception) {
    flash('error', $exception->getMessage());
    redirect_to('reports.php');
}

$logs = $pdo->query(
    'SELECT admin_logs.*, users.first_name, users.last_name
     FROM admin_logs
     LEFT JOIN users ON users.id = admin_logs.admin_user_id
     ORDER BY admin_logs.created_at DESC, admin_logs.id DESC'
)->fetchAll();

admin_header('Reports', 'reports', 'Review admin activity logs and operational audit records.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Admin Activity Logs</h2>
        <span class="text-secondary small"><?= count($logs) ?> total records</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Admin</th><th>Action</th><th>Table</th><th>Record</th><th>Description</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= e(trim(array_get($log, 'first_name', '') . ' ' . array_get($log, 'last_name', '')) ?: 'Unknown') ?></td>
                            <td class="fw-semibold"><?= e($log['action']) ?></td>
                            <td><?= e($log['table_name']) ?></td>
                            <td><?= e(array_get($log, 'record_id', '-')) ?></td>
                            <td class="text-secondary"><?= e(array_get($log, 'description', '')) ?></td>
                            <td><?= e($log['created_at']) ?></td>
                            <td class="text-end">
                                <form class="d-inline" method="post" data-confirm="Delete this log entry?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="log_id" value="<?= (int) $log['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?><tr><td colspan="7" class="text-center text-secondary py-4">No logs yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php admin_footer(); ?>
