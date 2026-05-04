<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

$count = function ($sql) use ($pdo) {
    return (int) $pdo->query($sql)->fetchColumn();
};

$stats = [
    ['label' => 'Total Users', 'value' => $count('SELECT COUNT(*) FROM users'), 'note' => 'All registered accounts', 'color' => 'primary'],
    ['label' => 'Active Rides', 'value' => $count("SELECT COUNT(*) FROM rides WHERE ride_status NOT IN ('completed', 'cancelled')"), 'note' => 'Currently tracked trips', 'color' => 'success'],
    ['label' => 'Pending Riders', 'value' => $count("SELECT COUNT(*) FROM drivers WHERE approval_status = 'pending'"), 'note' => 'Waiting for approval', 'color' => 'warning'],
    ['label' => 'Unread Alerts', 'value' => $count('SELECT COUNT(*) FROM notifications WHERE is_read = 0'), 'note' => 'Needs attention', 'color' => 'danger'],
];

$bookings = $pdo->query(
    'SELECT bookings.*, student_users.first_name AS student_first_name, student_users.last_name AS student_last_name,
        driver_users.first_name AS driver_first_name, driver_users.last_name AS driver_last_name
     FROM bookings
     JOIN students ON students.id = bookings.student_id
     JOIN users student_users ON student_users.id = students.user_id
     LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
     LEFT JOIN users driver_users ON driver_users.id = drivers.user_id
     ORDER BY bookings.updated_at DESC
     LIMIT 6'
)->fetchAll();

$verifications = $pdo->query(
    'SELECT drivers.id, users.first_name, users.last_name, drivers.license_number, drivers.approval_status
     FROM drivers
     JOIN users ON users.id = drivers.user_id
     WHERE drivers.approval_status = "pending"
     ORDER BY drivers.created_at DESC
     LIMIT 5'
)->fetchAll();

$logs = $pdo->query('SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 5')->fetchAll();

admin_header('Dashboard', 'dashboard', 'Monitor trips, account approvals, alerts, and daily operations.');
?>

<div class="row g-3 mb-4">
    <?php foreach ($stats as $stat): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-secondary small fw-semibold"><?= e($stat['label']) ?></span>
                        <span class="stat-icon bg-<?= e($stat['color']) ?>-subtle text-<?= e($stat['color']) ?>"><i class="bi bi-circle-fill"></i></span>
                    </div>
                    <h2 class="mb-1"><?= e($stat['value']) ?></h2>
                    <p class="text-secondary small mb-0"><?= e($stat['note']) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between gap-2">
                <div><h2 class="h6 mb-0">Recent Bookings</h2><span class="text-secondary small">Latest trip activity</span></div>
                <a class="btn btn-sm btn-outline-primary" href="bookings.php">Open bookings</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Schedule</th><th>Student</th><th>Rider</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($booking['scheduled_date']) ?><div class="text-secondary small"><?= e(substr((string) $booking['scheduled_time'], 0, 5)) ?></div></td>
                                    <td><?= e(trim($booking['student_first_name'] . ' ' . $booking['student_last_name'])) ?></td>
                                    <td><?= e(trim(array_get($booking, 'driver_first_name', '') . ' ' . array_get($booking, 'driver_last_name', '')) ?: 'Unassigned') ?></td>
                                    <td><span class="badge text-bg-primary"><?= e($booking['booking_status']) ?></span></td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="bookings.php">Manage</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$bookings): ?><tr><td colspan="5" class="text-center text-secondary py-4">No bookings yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Pending Rider Verifications</h2></div>
            <div class="list-group list-group-flush">
                <?php foreach ($verifications as $rider): ?>
                    <a class="list-group-item list-group-item-action py-3" href="riders.php">
                        <div class="fw-semibold"><?= e(trim($rider['first_name'] . ' ' . $rider['last_name'])) ?></div>
                        <div class="text-secondary small">License <?= e($rider['license_number']) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$verifications): ?><div class="list-group-item text-secondary py-3">No pending riders.</div><?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Recent Admin Logs</h2>
                <a class="small text-decoration-none" href="reports.php">View all</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($logs as $log): ?>
                    <div class="list-group-item py-3">
                        <div class="fw-semibold"><?= e($log['action']) ?></div>
                        <div class="text-secondary small"><?= e($log['created_at']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$logs): ?><div class="list-group-item text-secondary py-3">No admin logs yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php admin_footer(); ?>
