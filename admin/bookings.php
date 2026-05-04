<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$statuses = ['pending', 'approved', 'assigned', 'driver_arriving', 'picked_up', 'in_transit', 'dropped_off', 'completed', 'cancelled'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');
        $driverId = post_value('assigned_driver_id') !== '' ? (int) post_value('assigned_driver_id') : null;
        $params = [(int) post_value('parent_id'), (int) post_value('student_id'), post_value('pickup_address'), post_value('dropoff_address'), post_value('scheduled_date'), post_value('scheduled_time'), post_value('trip_type'), post_value('notes') ?: null, post_value('booking_status'), $driverId];

        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO bookings (parent_id, student_id, pickup_address, dropoff_address, scheduled_date, scheduled_time, trip_type, notes, booking_status, assigned_driver_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute($params);
            flash('success', 'Booking created successfully.');
            redirect_to('bookings.php');
        }

        if ($action === 'update') {
            $params[] = (int) post_value('booking_id');
            $stmt = $pdo->prepare('UPDATE bookings SET parent_id = ?, student_id = ?, pickup_address = ?, dropoff_address = ?, scheduled_date = ?, scheduled_time = ?, trip_type = ?, notes = ?, booking_status = ?, assigned_driver_id = ? WHERE id = ?');
            $stmt->execute($params);
            flash('success', 'Booking updated successfully.');
            redirect_to('bookings.php');
        }

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM bookings WHERE id = ?')->execute([(int) post_value('booking_id')]);
            flash('success', 'Booking deleted successfully.');
            redirect_to('bookings.php');
        }
    }
} catch (Exception $exception) {
    flash('error', $exception->getMessage());
    redirect_to('bookings.php');
}

$parents = $pdo->query('SELECT parents.id, users.first_name, users.last_name FROM parents JOIN users ON users.id = parents.user_id ORDER BY users.last_name')->fetchAll();
$students = $pdo->query('SELECT students.id, students.parent_id, users.first_name, users.last_name FROM students JOIN users ON users.id = students.user_id ORDER BY users.last_name')->fetchAll();
$drivers = $pdo->query('SELECT drivers.id, users.first_name, users.last_name FROM drivers JOIN users ON users.id = drivers.user_id ORDER BY users.last_name')->fetchAll();
$bookings = $pdo->query(
    'SELECT bookings.*, student_users.first_name AS student_first_name, student_users.last_name AS student_last_name,
        parent_users.first_name AS parent_first_name, parent_users.last_name AS parent_last_name,
        driver_users.first_name AS driver_first_name, driver_users.last_name AS driver_last_name
     FROM bookings
     JOIN students ON students.id = bookings.student_id
     JOIN users student_users ON student_users.id = students.user_id
     JOIN parents ON parents.id = bookings.parent_id
     JOIN users parent_users ON parent_users.id = parents.user_id
     LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
     LEFT JOIN users driver_users ON driver_users.id = drivers.user_id
     ORDER BY bookings.scheduled_date DESC, bookings.scheduled_time DESC'
)->fetchAll();

admin_header('Bookings', 'bookings', 'Approve trips, assign riders, and update booking status.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Trip Bookings</h2><span class="text-secondary small"><?= count($bookings) ?> total records</span></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createBookingModal" <?= (!$parents || !$students) ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Add booking</button>
    </div>
    <div class="card-body">
        <?php if (!$parents || !$students): ?><div class="alert alert-warning">Create parents and students before adding bookings.</div><?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Date</th><th>Student</th><th>Parent</th><th>Rider</th><th>Trip</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($booking['scheduled_date']) ?><div class="text-secondary small"><?= e(substr((string) $booking['scheduled_time'], 0, 5)) ?></div></td>
                            <td><?= e(trim($booking['student_first_name'] . ' ' . $booking['student_last_name'])) ?></td>
                            <td><?= e(trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name'])) ?></td>
                            <td><?= e(trim(array_get($booking, 'driver_first_name', '') . ' ' . array_get($booking, 'driver_last_name', '')) ?: 'Unassigned') ?></td>
                            <td><?= e($booking['trip_type']) ?></td>
                            <td><span class="badge text-bg-primary"><?= e($booking['booking_status']) ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editBooking<?= (int) $booking['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this booking?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$bookings): ?><tr><td colspan="7" class="text-center text-secondary py-4">No bookings yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $record = ['booking_status' => 'pending', 'trip_type' => 'one_way', 'scheduled_date' => date('Y-m-d'), 'scheduled_time' => date('H:i')]; ?>
<div class="modal fade" id="createBookingModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="modal-header"><h3 class="modal-title h5">Add Booking</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><?php include __DIR__ . '/partials/booking_fields.php'; ?></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save booking</button></div>
</form></div></div>

<?php foreach ($bookings as $record): ?>
    <div class="modal fade" id="editBooking<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="booking_id" value="<?= (int) $record['id'] ?>">
        <div class="modal-header"><h3 class="modal-title h5">Edit Booking</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><?php include __DIR__ . '/partials/booking_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update booking</button></div>
    </form></div></div>
<?php endforeach; ?>

<?php admin_footer(); ?>
