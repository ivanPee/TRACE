<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');

        if ($action === 'create') {
            $pdo->beginTransaction();
            $userId = create_user('parent', $_POST);
            $stmt = $pdo->prepare('INSERT INTO parents (user_id, address, valid_id_path, emergency_contact_name, emergency_contact_number) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, post_value('address'), post_value('valid_id_path') ?: null, post_value('emergency_contact_name'), post_value('emergency_contact_number')]);
            $pdo->commit();
            flash('success', 'Parent created successfully.');
            redirect_to('parents.php');
        }

        if ($action === 'update') {
            $pdo->beginTransaction();
            update_user((int) post_value('user_id'), $_POST + ['role_code' => 'parent']);
            $stmt = $pdo->prepare('UPDATE parents SET address = ?, valid_id_path = ?, emergency_contact_name = ?, emergency_contact_number = ? WHERE id = ?');
            $stmt->execute([post_value('address'), post_value('valid_id_path') ?: null, post_value('emergency_contact_name'), post_value('emergency_contact_number'), (int) post_value('parent_id')]);
            $pdo->commit();
            flash('success', 'Parent updated successfully.');
            redirect_to('parents.php');
        }

        if ($action === 'delete') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM parents WHERE id = ?')->execute([(int) post_value('parent_id')]);
            delete_user_tree((int) post_value('user_id'));
            $pdo->commit();
            flash('success', 'Parent deleted successfully.');
            redirect_to('parents.php');
        }
    }
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
    redirect_to('parents.php');
}

$parents = $pdo->query(
    'SELECT parents.*, users.first_name, users.middle_name, users.last_name, users.email, users.mobile_number, users.status, users.is_verified,
        (SELECT COUNT(*) FROM students WHERE students.parent_id = parents.id) AS student_count
     FROM parents
     JOIN users ON users.id = parents.user_id
     ORDER BY parents.created_at DESC, parents.id DESC'
)->fetchAll();
$roles = $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();

admin_header('Parents', 'parents', 'Manage guardian profiles, emergency contacts, and account status.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Parent Directory</h2><span class="text-secondary small"><?= count($parents) ?> total records</span></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createParentModal"><i class="bi bi-plus-lg me-1"></i>Add parent</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Parent</th><th>Contact</th><th>Address</th><th>Emergency</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($parents as $parent): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(full_name($parent)) ?></td>
                            <td><div><?= e($parent['email']) ?></div><span class="text-secondary small"><?= e($parent['mobile_number']) ?></span></td>
                            <td class="text-secondary"><?= e($parent['address']) ?></td>
                            <td><?= e($parent['emergency_contact_name']) ?><div class="text-secondary small"><?= e($parent['emergency_contact_number']) ?></div></td>
                            <td><?= (int) $parent['student_count'] ?></td>
                            <td><span class="badge text-bg-<?= $parent['status'] === 'active' ? 'success' : ($parent['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e($parent['status']) ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editParent<?= (int) $parent['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this parent? Delete will fail if students or bookings still depend on this record.">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="parent_id" value="<?= (int) $parent['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $parent['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$parents): ?><tr><td colspan="7" class="text-center text-secondary py-4">No parents yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $record = ['status' => 'active']; ?>
<div class="modal fade" id="createParentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h3 class="modal-title h5">Add Parent</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><?php include __DIR__ . '/partials/parent_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save parent</button></div>
    </form></div>
</div>

<?php foreach ($parents as $record): ?>
    <div class="modal fade" id="editParent<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="parent_id" value="<?= (int) $record['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $record['user_id'] ?>">
            <div class="modal-header"><h3 class="modal-title h5">Edit Parent</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><?php include __DIR__ . '/partials/parent_fields.php'; ?></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update parent</button></div>
        </form></div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>
