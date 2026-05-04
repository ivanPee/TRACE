<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');

        if ($action === 'create') {
            $pdo->beginTransaction();
            $userId = create_user('student', $_POST + ['status' => post_value('user_status', 'active')]);
            $stmt = $pdo->prepare(
                'INSERT INTO students (user_id, parent_id, lrn, school_name, grade_level, pickup_address, dropoff_address, medical_notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, (int) post_value('parent_id'), post_value('lrn'), post_value('school_name'), post_value('grade_level'), post_value('pickup_address'), post_value('dropoff_address'), post_value('medical_notes') ?: null, post_value('student_status', 'active')]);
            $pdo->commit();
            flash('success', 'Student created successfully.');
            redirect_to('students.php');
        }

        if ($action === 'update') {
            $pdo->beginTransaction();
            $_POST['status'] = post_value('user_status', 'active');
            update_user((int) post_value('user_id'), $_POST + ['role_code' => 'student']);
            $stmt = $pdo->prepare('UPDATE students SET parent_id = ?, lrn = ?, school_name = ?, grade_level = ?, pickup_address = ?, dropoff_address = ?, medical_notes = ?, status = ? WHERE id = ?');
            $stmt->execute([(int) post_value('parent_id'), post_value('lrn'), post_value('school_name'), post_value('grade_level'), post_value('pickup_address'), post_value('dropoff_address'), post_value('medical_notes') ?: null, post_value('student_status', 'active'), (int) post_value('student_id')]);
            $pdo->commit();
            flash('success', 'Student updated successfully.');
            redirect_to('students.php');
        }

        if ($action === 'delete') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([(int) post_value('student_id')]);
            delete_user_tree((int) post_value('user_id'));
            $pdo->commit();
            flash('success', 'Student deleted successfully.');
            redirect_to('students.php');
        }
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
    redirect_to('students.php');
}

$parents = $pdo->query(
    'SELECT parents.id, users.first_name, users.last_name
     FROM parents
     JOIN users ON users.id = parents.user_id
     ORDER BY users.last_name, users.first_name'
)->fetchAll();
$students = $pdo->query(
    'SELECT students.*, users.first_name, users.middle_name, users.last_name, users.email, users.mobile_number, users.status AS user_status, users.is_verified,
        parent_users.first_name AS parent_first_name, parent_users.last_name AS parent_last_name
     FROM students
     JOIN users ON users.id = students.user_id
     JOIN parents ON parents.id = students.parent_id
     JOIN users parent_users ON parent_users.id = parents.user_id
     ORDER BY students.created_at DESC, students.id DESC'
)->fetchAll();

admin_header('Students', 'students', 'Manage student records, parent links, school information, and pickup details.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Student Records</h2><span class="text-secondary small"><?= count($students) ?> total records</span></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createStudentModal" <?= !$parents ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Add student</button>
    </div>
    <div class="card-body">
        <?php if (!$parents): ?>
            <div class="alert alert-warning">Create at least one parent before adding students.</div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Student</th><th>Parent</th><th>LRN</th><th>School</th><th>Grade</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(full_name($student)) ?><div class="text-secondary small"><?= e($student['email']) ?></div></td>
                            <td><?= e(trim($student['parent_first_name'] . ' ' . $student['parent_last_name'])) ?></td>
                            <td><?= e($student['lrn']) ?></td>
                            <td><?= e($student['school_name']) ?></td>
                            <td><?= e($student['grade_level']) ?></td>
                            <td><span class="badge text-bg-<?= $student['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($student['status']) ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editStudent<?= (int) $student['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this student? Delete will fail if bookings still depend on this record.">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $student['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$students): ?><tr><td colspan="7" class="text-center text-secondary py-4">No students yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $record = ['student_status' => 'active', 'user_status' => 'active']; ?>
<div class="modal fade" id="createStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h3 class="modal-title h5">Add Student</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><?php include __DIR__ . '/partials/student_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save student</button></div>
    </form></div>
</div>

<?php foreach ($students as $record): ?>
    <div class="modal fade" id="editStudent<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="student_id" value="<?= (int) $record['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $record['user_id'] ?>">
            <div class="modal-header"><h3 class="modal-title h5">Edit Student</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><?php include __DIR__ . '/partials/student_fields.php'; ?></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update student</button></div>
        </form></div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>

