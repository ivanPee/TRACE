<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();
admin_ensure_monthly_plan_schema();
ensure_booking_recurring_group_column();

$scalar = function (string $sql, $fallback = 0) use ($pdo) {
    try {
        return $pdo->query($sql)->fetchColumn() ?: $fallback;
    } catch (Throwable $exception) {
        return $fallback;
    }
};

$money = fn ($value) => 'PHP ' . number_format((float) $value, 2);
$today = date('Y-m-d');

$totalUsers = (int) $scalar('SELECT COUNT(*) FROM users');
$totalParents = (int) $scalar('SELECT COUNT(*) FROM parents');
$totalChildren = (int) $scalar('SELECT COUNT(*) FROM students');
$activeDrivers = (int) $scalar("SELECT COUNT(*) FROM drivers WHERE approval_status = 'approved'");
$onlineDrivers = (int) $scalar('SELECT COUNT(*) FROM drivers WHERE is_online = 1');
$pendingDrivers = (int) $scalar("SELECT COUNT(*) FROM drivers WHERE approval_status = 'pending'");
$activeSubscriptions = (int) $scalar("SELECT COUNT(*) FROM monthly_plans WHERE status = 'active'");
$subscriptionMonthlyValue = (float) $scalar("SELECT COALESCE(SUM(monthly_amount), 0) FROM monthly_plans WHERE status = 'active'", 0);
$todayBookings = (int) $scalar("SELECT COUNT(*) FROM bookings WHERE scheduled_date = CURDATE()");
$openBookings = (int) $scalar("SELECT COUNT(*) FROM bookings WHERE monthly_plan_id IS NOT NULL AND booking_status NOT IN ('completed', 'cancelled')");
$unassignedToday = (int) $scalar("SELECT COUNT(*) FROM bookings WHERE scheduled_date = CURDATE() AND assigned_driver_id IS NULL AND booking_status NOT IN ('completed', 'cancelled')");
$activeTrips = (int) $scalar("SELECT COUNT(*) FROM rides WHERE ride_status NOT IN ('completed', 'cancelled')");
$completedToday = (int) $scalar("SELECT COUNT(*) FROM rides JOIN bookings ON bookings.id = rides.booking_id WHERE bookings.scheduled_date = CURDATE() AND rides.ride_status = 'completed'");
$unreadAlerts = (int) $scalar('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
$todayPayouts = (float) $scalar("SELECT COALESCE(SUM(driver_payout_amount), 0) FROM bookings WHERE scheduled_date = CURDATE() AND booking_status = 'completed'", 0);
$completionRate = $todayBookings > 0 ? round(($completedToday / $todayBookings) * 100) : 0;

$metrics = [
    ['label' => 'Monthly Plans', 'value' => $activeSubscriptions, 'note' => 'Active value PHP ' . number_format($subscriptionMonthlyValue, 2), 'icon' => 'bi-calendar2-check', 'color' => '#078EFF'],
    ['label' => 'Active Trips', 'value' => $activeTrips, 'note' => 'Live or in-progress ride records', 'icon' => 'bi-broadcast-pin', 'color' => '#18a957'],
    ['label' => 'Driver Readiness', 'value' => $onlineDrivers . '/' . $activeDrivers, 'note' => $pendingDrivers . ' pending verification', 'icon' => 'bi-person-check', 'color' => '#d99a00'],
    ['label' => 'Open Alerts', 'value' => $unreadAlerts, 'note' => 'Unread operational notifications', 'icon' => 'bi-bell', 'color' => '#d64242'],
];

$activeTripRows = $pdo->query(
    'SELECT rides.id, rides.ride_status, rides.started_at, rides.picked_up_at, rides.dropped_off_at,
        bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_address, bookings.dropoff_address,
        child_users.first_name AS child_first_name, child_users.last_name AS child_last_name,
        driver_users.first_name AS driver_first_name, driver_users.last_name AS driver_last_name
     FROM rides
     JOIN bookings ON bookings.id = rides.booking_id
     JOIN students ON students.id = bookings.student_id
     JOIN users child_users ON child_users.id = students.user_id
     JOIN drivers ON drivers.id = rides.driver_id
     JOIN users driver_users ON driver_users.id = drivers.user_id
     WHERE rides.ride_status NOT IN ("completed", "cancelled")
     ORDER BY bookings.scheduled_date ASC, bookings.scheduled_time ASC, rides.updated_at DESC
     LIMIT 8'
)->fetchAll();

$openBookingRows = $pdo->query(
    'SELECT bookings.id, bookings.booking_status, bookings.scheduled_date, bookings.scheduled_time, bookings.assigned_driver_id,
        child_users.first_name AS child_first_name, child_users.last_name AS child_last_name,
        parent_users.first_name AS parent_first_name, parent_users.last_name AS parent_last_name,
        driver_users.first_name AS driver_first_name, driver_users.last_name AS driver_last_name
     FROM bookings
     JOIN students ON students.id = bookings.student_id
     JOIN users child_users ON child_users.id = students.user_id
     JOIN parents ON parents.id = bookings.parent_id
     JOIN users parent_users ON parent_users.id = parents.user_id
     LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
     LEFT JOIN users driver_users ON driver_users.id = drivers.user_id
     WHERE bookings.monthly_plan_id IS NOT NULL
       AND bookings.booking_status NOT IN ("completed", "cancelled")
     ORDER BY bookings.scheduled_date ASC, bookings.scheduled_time ASC, bookings.updated_at DESC
     LIMIT 10'
)->fetchAll();

$pendingDriverRows = $pdo->query(
    'SELECT drivers.id, drivers.license_number, drivers.license_expiry, drivers.vehicle_plate_number, drivers.vehicle_model,
        users.first_name, users.last_name, users.mobile_number
     FROM drivers
     JOIN users ON users.id = drivers.user_id
     WHERE drivers.approval_status = "pending"
     ORDER BY drivers.created_at DESC
     LIMIT 6'
)->fetchAll();

$recentAlerts = $pdo->query(
    'SELECT notifications.*, users.first_name, users.last_name
     FROM notifications
     JOIN users ON users.id = notifications.user_id
     ORDER BY notifications.created_at DESC, notifications.id DESC
     LIMIT 6'
)->fetchAll();

$fleetRows = $pdo->query(
    'SELECT drivers.approval_status, COUNT(*) AS total
     FROM drivers
     GROUP BY drivers.approval_status
     ORDER BY total DESC'
)->fetchAll();

admin_header('Operations Dashboard', 'dashboard', 'Live dispatch, safety, account verification, and route coverage.');
?>

<div class="row g-3 mb-4">
    <?php foreach ($metrics as $metric): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100" style="--metric-color: <?= e($metric['color']) ?>">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="metric-label"><?= e($metric['label']) ?></div>
                            <div class="metric-value"><?= e($metric['value']) ?></div>
                            <div class="metric-note"><?= e($metric['note']) ?></div>
                        </div>
                        <div class="metric-icon"><i class="bi <?= e($metric['icon']) ?>"></i></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="admin-card-title">Subscription Route Board</h2>
                    <div class="admin-card-subtitle">Generated trips from monthly plan subscriptions, sorted by schedule urgency</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="bookings.php">Manage plans</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr><th>Schedule</th><th>Child</th><th>Parent</th><th>Driver</th><th>Status</th><th class="text-end">Record</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openBookingRows as $booking): ?>
                                <tr>
                                    <td>
                                        <div class="record-title"><?= e($booking['scheduled_date']) ?></div>
                                        <div class="record-meta"><?= e(substr((string) $booking['scheduled_time'], 0, 5)) ?></div>
                                    </td>
                                    <td><?= e(trim($booking['child_first_name'] . ' ' . $booking['child_last_name'])) ?></td>
                                    <td><?= e(trim($booking['parent_first_name'] . ' ' . $booking['parent_last_name'])) ?></td>
                                    <td><?= e(trim((string) ($booking['driver_first_name'] ?? '') . ' ' . (string) ($booking['driver_last_name'] ?? '')) ?: 'Unassigned') ?></td>
                                    <td><?= admin_status_badge($booking['booking_status']) ?></td>
                                    <td class="text-end"><a class="btn btn-sm btn-light border" href="bookings.php">Trip #<?= e($booking['id']) ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$openBookingRows): ?><tr><td colspan="6" class="text-center text-secondary py-4">No generated subscription trips are open.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h2 class="admin-card-title">Today Performance</h2>
                <div class="admin-card-subtitle"><?= e($today) ?> service health</div>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-end justify-content-between mb-2">
                    <div>
                        <div class="metric-label">Completion Rate</div>
                        <div class="metric-value"><?= e($completionRate) ?>%</div>
                    </div>
                    <?= admin_status_badge($completionRate >= 80 ? 'success' : ($completionRate >= 40 ? 'pending' : 'danger')) ?>
                </div>
                <div class="progress mb-4" role="progressbar" aria-label="Completion rate" aria-valuenow="<?= e($completionRate) ?>" aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
                    <div class="progress-bar" style="width: <?= e($completionRate) ?>%"></div>
                </div>
                <div class="detail-grid">
                    <div class="detail-card"><h4>Completed</h4><p><?= e($completedToday) ?> of <?= e($todayBookings) ?></p></div>
                    <div class="detail-card"><h4>Unassigned</h4><p><?= e($unassignedToday) ?></p></div>
                    <div class="detail-card"><h4>Expected Payout</h4><p><?= e($money($todayPayouts)) ?></p></div>
                    <div class="detail-card"><h4>Accounts</h4><p><?= e($totalParents) ?> parents / <?= e($totalChildren) ?> children</p></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="admin-card-title">Live Trips</h2>
                    <div class="admin-card-subtitle">Current movement and pickup/drop-off status</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="reports.php">Trip report</a>
            </div>
            <div class="card-body">
                <div class="ops-list">
                    <?php foreach ($activeTripRows as $trip): ?>
                        <div class="ops-item">
                            <div>
                                <div class="record-title">Trip #<?= e($trip['id']) ?> · <?= e(trim($trip['child_first_name'] . ' ' . $trip['child_last_name'])) ?></div>
                                <div class="record-meta">
                                    <?= e($trip['scheduled_date']) ?> <?= e(substr((string) $trip['scheduled_time'], 0, 5)) ?>
                                    · <?= e(trim($trip['driver_first_name'] . ' ' . $trip['driver_last_name'])) ?>
                                </div>
                            </div>
                            <?= admin_status_badge($trip['ride_status']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$activeTripRows): ?><div class="text-secondary py-2">No active trips right now.</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="admin-card-title">Driver Verification Queue</h2>
                    <div class="admin-card-subtitle">Documents waiting for admin review</div>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="drivers.php">Review</a>
            </div>
            <div class="card-body">
                <div class="ops-list">
                    <?php foreach ($pendingDriverRows as $driver): ?>
                        <a class="ops-item text-decoration-none text-reset" href="drivers.php">
                            <div>
                                <div class="record-title"><?= e(trim($driver['first_name'] . ' ' . $driver['last_name'])) ?></div>
                                <div class="record-meta"><?= e($driver['vehicle_model']) ?> · <?= e($driver['vehicle_plate_number']) ?> · License <?= e($driver['license_number']) ?></div>
                            </div>
                            <i class="bi bi-chevron-right text-secondary"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$pendingDriverRows): ?><div class="text-secondary py-2">No pending driver approvals.</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3">
        <div class="card h-100">
            <div class="card-header">
                <h2 class="admin-card-title">System Snapshot</h2>
                <div class="admin-card-subtitle">Fleet, account, and alert totals</div>
            </div>
            <div class="card-body">
                <div class="detail-grid mb-3">
                    <div class="detail-card"><h4>Total users</h4><p><?= e($totalUsers) ?></p></div>
                    <div class="detail-card"><h4>Open subscription trips</h4><p><?= e($openBookings) ?></p></div>
                </div>
                <div class="ops-list">
                    <?php foreach ($fleetRows as $fleet): ?>
                        <div class="ops-item">
                            <span><?= admin_status_badge($fleet['approval_status']) ?></span>
                            <strong><?= e($fleet['total']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                <hr>
                <div class="ops-list">
                    <?php foreach ($recentAlerts as $alert): ?>
                        <div>
                            <div class="record-title"><?= e($alert['title']) ?></div>
                            <div class="record-meta"><?= e(trim($alert['first_name'] . ' ' . $alert['last_name'])) ?> · <?= e($alert['created_at']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recentAlerts): ?><div class="text-secondary py-2">No alerts recorded.</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php admin_footer(); ?>
