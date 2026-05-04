<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');

        if ($action === 'create') {
            create_user((string) post_value('role_code'), $_POST);
            flash('success', 'User account created successfully.');
            redirect_to('users.php');
        }

        if ($action === 'update') {
            update_user((int) post_value('user_id'), $_POST);
            flash('success', 'User account updated successfully.');
            redirect_to('users.php');
        }

        if ($action === 'delete') {
            delete_user_tree((int) post_value('user_id'));
            flash('success', 'User account deleted successfully.');
            redirect_to('users.php');
        }
    }
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
    redirect_to('users.php');
}

$roles = $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();
$users = $pdo->query(
    'SELECT users.*, roles.code AS role_code, roles.name AS role_name
     FROM users
     JOIN roles ON roles.id = users.role_id
     ORDER BY users.created_at DESC, users.id DESC'
)->fetchAll();

admin_header('Users', 'users', 'Create, update, verify, suspend, and delete system accounts.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h6 mb-0">User Accounts</h2>
            <span class="text-secondary small"><?= count($users) ?> total records</span>
        </div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-plus-lg me-1"></i>Add user
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(full_name($user)) ?></td>
                            <td><?= e($user['role_name']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><?= e($user['mobile_number']) ?></td>
                            <td><span class="badge text-bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e($user['status']) ?></span></td>
                            <td><?= (int) $user['is_verified'] === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-light border">No</span>' ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editUser<?= (int) $user['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form class="d-inline" method="post" data-confirm="Delete this user account and its direct messages/alerts?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-4">No users yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h3 class="modal-title h5">Add User</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php include __DIR__ . '/partials/user_fields.php'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save user</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($users as $record): ?>
    <div class="modal fade" id="editUser<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?= (int) $record['id'] ?>">
                <div class="modal-header">
                    <h3 class="modal-title h5">Edit User</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php include __DIR__ . '/partials/user_fields.php'; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update user</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>

