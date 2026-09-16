<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();
admin_ensure_monthly_plan_schema();

$count = function ($sql) use ($pdo) {
    return (int) $pdo->query($sql)->fetchColumn();
};

$sum = function ($sql) use ($pdo) {
    return (float) ($pdo->query($sql)->fetchColumn() ?: 0);
};

$percent = function ($value, $total) {
    return $total > 0 ? round(($value / $total) * 100, 1) . '%' : '0%';
};

$money = function ($value) {
    return 'PHP ' . number_format((float) $value, 2);
};

function admin_finance_series(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT period,
            SUM(payments) AS payments,
            SUM(refunds) AS refunds,
            SUM(payouts) AS payouts
         FROM (
            SELECT DATE_FORMAT(COALESCE(paid_at, created_at), "%Y-%m") AS period,
                SUM(amount_paid) AS payments,
                0 AS refunds,
                0 AS payouts
            FROM service_payments
            WHERE status = "paid"
            GROUP BY DATE_FORMAT(COALESCE(paid_at, created_at), "%Y-%m")
            UNION ALL
            SELECT DATE_FORMAT(COALESCE(refunded_at, created_at), "%Y-%m") AS period,
                0 AS payments,
                SUM(refund_amount) AS refunds,
                0 AS payouts
            FROM monthly_plan_refunds
            WHERE status IN ("approved", "paid")
            GROUP BY DATE_FORMAT(COALESCE(refunded_at, created_at), "%Y-%m")
            UNION ALL
            SELECT DATE_FORMAT(COALESCE(paid_at, created_at), "%Y-%m") AS period,
                0 AS payments,
                0 AS refunds,
                SUM(amount) AS payouts
            FROM driver_payouts
            WHERE status = "paid"
            GROUP BY DATE_FORMAT(COALESCE(paid_at, created_at), "%Y-%m")
         ) finance
         WHERE period IS NOT NULL
         GROUP BY period
         ORDER BY period DESC
         LIMIT 6'
    )->fetchAll();
    $series = array_reverse($rows);
    $max = 1.0;

    foreach ($series as &$row) {
        $row['payments'] = (float) $row['payments'];
        $row['refunds'] = (float) $row['refunds'];
        $row['payouts'] = (float) $row['payouts'];
        $row['label'] = date('M Y', strtotime($row['period'] . '-01'));
        $max = max($max, $row['payments'], $row['refunds'], $row['payouts']);
    }
    unset($row);

    return ['series' => $series, 'max' => $max, 'generatedAt' => date('M d, Y h:i:s A')];
}

if (isset($_GET['finance_series'])) {
    require_admin();
    header('Content-Type: application/json');
    echo json_encode(admin_finance_series($pdo));
    exit;
}

$totalUsers = $count('SELECT COUNT(*) FROM users');
$activeUsers = $count("SELECT COUNT(*) FROM users WHERE status = 'active'");
$verifiedUsers = $count('SELECT COUNT(*) FROM users WHERE is_verified = 1');
$totalParents = $count('SELECT COUNT(*) FROM parents');
$totalStudents = $count('SELECT COUNT(*) FROM students');
$activeStudents = $count("SELECT COUNT(*) FROM students WHERE status = 'active'");
$totalDrivers = $count('SELECT COUNT(*) FROM drivers');
$approvedDrivers = $count("SELECT COUNT(*) FROM drivers WHERE approval_status = 'approved'");
$pendingDrivers = $count("SELECT COUNT(*) FROM drivers WHERE approval_status = 'pending'");
$onlineDrivers = $count('SELECT COUNT(*) FROM drivers WHERE is_online = 1');
$totalVehicles = $count('SELECT COUNT(*) FROM vehicles');
$activeVehicles = $count("SELECT COUNT(*) FROM vehicles WHERE status = 'active'");
$maintenanceVehicles = $count("SELECT COUNT(*) FROM vehicles WHERE status = 'maintenance'");
$totalBookings = $count('SELECT COUNT(*) FROM bookings');
$todayBookings = $count('SELECT COUNT(*) FROM bookings WHERE scheduled_date = CURDATE()');
$upcomingBookings = $count("SELECT COUNT(*) FROM bookings WHERE scheduled_date >= CURDATE() AND booking_status NOT IN ('completed', 'cancelled')");
$unassignedBookings = $count("SELECT COUNT(*) FROM bookings WHERE assigned_driver_id IS NULL AND booking_status NOT IN ('completed', 'cancelled')");
$completedBookings = $count("SELECT COUNT(*) FROM bookings WHERE booking_status = 'completed'");
$cancelledBookings = $count("SELECT COUNT(*) FROM bookings WHERE booking_status = 'cancelled'");
$totalRides = $count('SELECT COUNT(*) FROM rides');
$activeTrips = $count("SELECT COUNT(*) FROM rides WHERE ride_status NOT IN ('completed', 'cancelled')");
$completedTrips = $count("SELECT COUNT(*) FROM rides WHERE ride_status = 'completed'");
$cancelledTrips = $count("SELECT COUNT(*) FROM rides WHERE ride_status = 'cancelled'");
$unreadAlerts = $count('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
$unreadMessages = $count('SELECT COUNT(*) FROM messages WHERE is_read = 0');
$activeMonthlyPlans = $count("SELECT COUNT(*) FROM monthly_plans WHERE status = 'active'");
$pendingPayments = $count("SELECT COUNT(*) FROM service_payments WHERE status = 'pending'");
$paidPayments = $count("SELECT COUNT(*) FROM service_payments WHERE status = 'paid'");
$amountDue = $sum("SELECT SUM(amount_due) FROM service_payments WHERE status <> 'cancelled'");
$amountPaid = $sum("SELECT SUM(amount_paid) FROM service_payments WHERE status = 'paid'");
$refundCount = $count("SELECT COUNT(*) FROM monthly_plan_refunds WHERE status <> 'cancelled'");
$approvedRefunds = $count("SELECT COUNT(*) FROM monthly_plan_refunds WHERE status IN ('approved', 'paid')");
$totalRefunds = $sum("SELECT SUM(refund_amount) FROM monthly_plan_refunds WHERE status IN ('approved', 'paid')");
$driverPayouts = $sum("SELECT SUM(amount) FROM driver_payouts WHERE status = 'paid'");
$netCollected = $amountPaid - $totalRefunds;

$roleBreakdown = $pdo->query(
    'SELECT roles.name, roles.code, COUNT(users.id) AS total
     FROM roles
     LEFT JOIN users ON users.role_id = roles.id
     GROUP BY roles.id, roles.name, roles.code
     ORDER BY roles.id'
)->fetchAll();

$bookingStatusRows = $pdo->query(
    'SELECT booking_status AS label, COUNT(*) AS total
     FROM bookings
     GROUP BY booking_status
     ORDER BY total DESC, booking_status ASC'
)->fetchAll();

$tripStatusRows = $pdo->query(
    'SELECT ride_status AS label, COUNT(*) AS total
     FROM rides
     GROUP BY ride_status
     ORDER BY total DESC, ride_status ASC'
)->fetchAll();

$driverStatusRows = $pdo->query(
    'SELECT approval_status AS label, COUNT(*) AS total
     FROM drivers
     GROUP BY approval_status
     ORDER BY total DESC, approval_status ASC'
)->fetchAll();

$recentTrips = $pdo->query(
    'SELECT rides.id, rides.ride_status, bookings.scheduled_date, bookings.scheduled_time,
        student_users.first_name AS student_first_name, student_users.last_name AS student_last_name,
        driver_users.first_name AS driver_first_name, driver_users.last_name AS driver_last_name
     FROM rides
     JOIN bookings ON bookings.id = rides.booking_id
     JOIN students ON students.id = bookings.student_id
     JOIN users student_users ON student_users.id = students.user_id
     JOIN drivers ON drivers.id = rides.driver_id
     JOIN users driver_users ON driver_users.id = drivers.user_id
     ORDER BY COALESCE(rides.completed_at, rides.updated_at, rides.created_at) DESC, rides.id DESC
     LIMIT 8'
)->fetchAll();

$monthlyBookingRows = $pdo->query(
    'SELECT DATE_FORMAT(scheduled_date, "%Y-%m") AS period,
        COUNT(*) AS total,
        SUM(booking_status = "completed") AS completed,
        SUM(booking_status = "cancelled") AS cancelled
     FROM bookings
     GROUP BY DATE_FORMAT(scheduled_date, "%Y-%m")
     ORDER BY period DESC
     LIMIT 6'
)->fetchAll();

$financeData = admin_finance_series($pdo);
$financeSeries = $financeData['series'];
$financeMax = $financeData['max'];

$recentRefunds = $pdo->query(
    'SELECT monthly_plan_refunds.*,
        parent_users.first_name AS parent_first_name,
        parent_users.last_name AS parent_last_name,
        monthly_plans.billing_month,
        monthly_plans.trip_type,
        monthly_plan_routes.destination_address
     FROM monthly_plan_refunds
     JOIN parents ON parents.id = monthly_plan_refunds.parent_id
     JOIN users parent_users ON parent_users.id = parents.user_id
     LEFT JOIN monthly_plans ON monthly_plans.id = monthly_plan_refunds.monthly_plan_id
     LEFT JOIN monthly_plan_routes ON monthly_plan_routes.id = monthly_plan_refunds.monthly_plan_route_id
     ORDER BY COALESCE(monthly_plan_refunds.refunded_at, monthly_plan_refunds.created_at) DESC, monthly_plan_refunds.id DESC
     LIMIT 8'
)->fetchAll();

$kpis = [
    ['label' => 'Total Users', 'value' => $totalUsers, 'note' => $percent($activeUsers, $totalUsers) . ' active accounts', 'color' => 'primary', 'icon' => 'bi-people'],
    ['label' => 'Active Trips', 'value' => $activeTrips, 'note' => $completedTrips . ' completed trips', 'color' => 'success', 'icon' => 'bi-signpost-split'],
    ['label' => 'Pending Drivers', 'value' => $pendingDrivers, 'note' => $approvedDrivers . ' approved drivers', 'color' => 'warning', 'icon' => 'bi-person-badge'],
    ['label' => 'Refunds', 'value' => $money($totalRefunds), 'note' => $approvedRefunds . ' approved no-class refunds', 'color' => 'danger', 'icon' => 'bi-arrow-counterclockwise'],
];

admin_header('Reports', 'reports', 'Operational reports for users, trips, drivers, fleet, communication, and payments.');
?>

<div class="row g-3 mb-4">
    <?php foreach ($kpis as $stat): ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-secondary small fw-semibold"><?= e($stat['label']) ?></span>
                        <span class="stat-icon bg-<?= e($stat['color']) ?>-subtle text-<?= e($stat['color']) ?>"><i class="bi <?= e($stat['icon']) ?>"></i></span>
                    </div>
                    <h2 class="mb-1"><?= e($stat['value']) ?></h2>
                    <p class="text-secondary small mb-0"><?= e($stat['note']) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h6 mb-0">Monthly Financial Movement</h2>
            <span class="text-secondary small">Payments collected, no-class refunds recorded, and driver payouts by month</span>
        </div>
        <span class="badge text-bg-light border" data-finance-updated>Updated <?= e($financeData['generatedAt']) ?></span>
    </div>
    <div class="card-body">
        <div class="finance-chart <?= $financeSeries ? '' : 'd-none' ?>" role="img" aria-label="Monthly finance bar graph" data-finance-chart>
            <?php if ($financeSeries): ?>
                <?php foreach ($financeSeries as $row): ?>
                    <?php
                        $paymentValue = (float) $row['payments'];
                        $refundValue = (float) $row['refunds'];
                        $payoutValue = (float) $row['payouts'];
                    ?>
                    <div class="finance-chart-group">
                        <div class="finance-bars">
                            <div class="finance-bar finance-bar-payment" style="height: <?= e(max(4, round(($paymentValue / $financeMax) * 100))) ?>%;" title="Payments <?= e($money($paymentValue)) ?>"></div>
                            <div class="finance-bar finance-bar-refund" style="height: <?= e(max(4, round(($refundValue / $financeMax) * 100))) ?>%;" title="Refunds <?= e($money($refundValue)) ?>"></div>
                            <div class="finance-bar finance-bar-payout" style="height: <?= e(max(4, round(($payoutValue / $financeMax) * 100))) ?>%;" title="Payouts <?= e($money($payoutValue)) ?>"></div>
                        </div>
                        <div class="finance-period"><?= e($row['label']) ?></div>
                        <div class="finance-values">
                            <span><?= e($money($paymentValue)) ?></span>
                            <span><?= e($money($refundValue)) ?></span>
                            <span><?= e($money($payoutValue)) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="finance-legend mt-3 <?= $financeSeries ? '' : 'd-none' ?>" data-finance-legend>
            <span><i class="finance-dot finance-dot-payment"></i>Payments</span>
            <span><i class="finance-dot finance-dot-refund"></i>Refunds</span>
            <span><i class="finance-dot finance-dot-payout"></i>Driver payouts</span>
        </div>
        <div class="text-secondary py-3 <?= $financeSeries ? 'd-none' : '' ?>" data-finance-empty>No payment, refund, or payout records are available for charting yet.</div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Operations Summary</h2>
                <span class="text-secondary small">Live totals generated from current system records</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><div class="border rounded-2 p-3 h-100"><div class="text-secondary small">Parents</div><div class="fs-4 fw-semibold"><?= e($totalParents) ?></div></div></div>
                    <div class="col-md-4"><div class="border rounded-2 p-3 h-100"><div class="text-secondary small">Children</div><div class="fs-4 fw-semibold"><?= e($totalStudents) ?></div><div class="text-secondary small"><?= e($activeStudents) ?> active</div></div></div>
                    <div class="col-md-4"><div class="border rounded-2 p-3 h-100"><div class="text-secondary small">Verified Users</div><div class="fs-4 fw-semibold"><?= e($verifiedUsers) ?></div><div class="text-secondary small"><?= e($percent($verifiedUsers, $totalUsers)) ?> of accounts</div></div></div>
                    <div class="col-md-4"><div class="border rounded-2 p-3 h-100"><div class="text-secondary small">Generated Trips Today</div><div class="fs-4 fw-semibold"><?= e($todayBookings) ?></div></div></div>
                    <div class="col-md-4"><div class="border rounded-2 p-3 h-100"><div class="text-secondary small">Upcoming Generated Trips</div><div class="fs-4 fw-semibold"><?= e($upcomingBookings) ?></div><div class="text-secondary small"><?= e($unassignedBookings) ?> unassigned</div></div></div>
                    <div class="col-md-4"><div class="border rounded-2 p-3 h-100"><div class="text-secondary small">Generated Trip Completion</div><div class="fs-4 fw-semibold"><?= e($percent($completedBookings, $totalBookings)) ?></div><div class="text-secondary small"><?= e($cancelledBookings) ?> cancelled</div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Driver and Fleet Readiness</h2>
                <span class="text-secondary small">Approval, availability, and vehicle coverage</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Approved drivers</span><strong><?= e($approvedDrivers) ?> / <?= e($totalDrivers) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Online drivers</span><strong><?= e($onlineDrivers) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Registered vehicles</span><strong><?= e($totalVehicles) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Active vehicles</span><strong><?= e($activeVehicles) ?></strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-secondary">In maintenance</span><strong><?= e($maintenanceVehicles) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Users by Role</h2></div>
            <div class="card-body">
                <?php foreach ($roleBreakdown as $row): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span><?= e($row['name']) ?></span>
                        <span class="badge text-bg-light border"><?= e($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Generated Trip Status</h2></div>
            <div class="card-body">
                <?php foreach ($bookingStatusRows as $row): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-capitalize"><?= e(str_replace('_', ' ', $row['label'])) ?></span>
                        <span class="badge text-bg-light border"><?= e($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$bookingStatusRows): ?><div class="text-secondary py-3">No generated trip records yet.</div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Trip and Driver Status</h2></div>
            <div class="card-body">
                <?php foreach ($tripStatusRows as $row): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-capitalize">Trip: <?= e(str_replace('_', ' ', $row['label'])) ?></span>
                        <span class="badge text-bg-light border"><?= e($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($driverStatusRows as $row): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-capitalize">Driver: <?= e($row['label']) ?></span>
                        <span class="badge text-bg-light border"><?= e($row['total']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$tripStatusRows && !$driverStatusRows): ?><div class="text-secondary py-3">No trip or driver records yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Monthly Generated Trip Performance</h2>
                <span class="text-secondary small">Last 6 scheduled months with generated subscription trips</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Month</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>Completion Rate</th></tr></thead>
                        <tbody>
                            <?php foreach ($monthlyBookingRows as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($row['period']) ?></td>
                                    <td><?= e($row['total']) ?></td>
                                    <td><?= e((int) $row['completed']) ?></td>
                                    <td><?= e((int) $row['cancelled']) ?></td>
                                    <td><?= e($percent((int) $row['completed'], (int) $row['total'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$monthlyBookingRows): ?><tr><td colspan="5" class="text-center text-secondary py-4">No generated trip performance data yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Payment Summary</h2>
                <span class="text-secondary small">Service billing and driver payouts</span>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Active monthly plans</span><strong><?= e($activeMonthlyPlans) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Pending payments</span><strong><?= e($pendingPayments) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Paid payments</span><strong><?= e($paidPayments) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Amount due</span><strong><?= e($money($amountDue)) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Amount paid</span><strong><?= e($money($amountPaid)) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Refunds recorded</span><strong><?= e($money($totalRefunds)) ?></strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-secondary">Net after refunds</span><strong><?= e($money($netCollected)) ?></strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-secondary">Driver payouts paid</span><strong><?= e($money($driverPayouts)) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h6 mb-0">Refund Ledger</h2>
            <span class="text-secondary small"><?= e($refundCount) ?> recorded refund<?= $refundCount === 1 ? '' : 's' ?> from cancelled monthly-plan service days</span>
        </div>
        <span class="badge text-bg-light border">Total <?= e($money($totalRefunds)) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Refund</th><th>Parent</th><th>Service Date</th><th>Route</th><th>Reason</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($recentRefunds as $refund): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($refund['reference_number'] ?: ('Refund #' . $refund['id'])) ?></div>
                                <div class="text-secondary small"><?= e($refund['refunded_at'] ?: $refund['created_at']) ?></div>
                            </td>
                            <td><?= e(trim($refund['parent_first_name'] . ' ' . $refund['parent_last_name'])) ?></td>
                            <td><?= e($refund['scheduled_date']) ?></td>
                            <td style="max-width: 280px; white-space: normal;"><?= e($refund['destination_address'] ?: 'Monthly plan route') ?></td>
                            <td style="max-width: 280px; white-space: normal;"><?= e($refund['reason'] ?: 'Cancelled daily trip') ?></td>
                            <td><?= admin_status_badge($refund['status']) ?></td>
                            <td class="text-end fw-semibold"><?= e($money($refund['refund_amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentRefunds): ?><tr><td colspan="7" class="text-center text-secondary py-4">No refund records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Recent Trips</h2>
        <span class="text-secondary small"><?= e($totalRides) ?> total trip records, <?= e($cancelledTrips) ?> cancelled</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Trip</th><th>Schedule</th><th>Child</th><th>Driver</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recentTrips as $trip): ?>
                        <tr>
                            <td class="fw-semibold">#<?= e($trip['id']) ?></td>
                            <td><?= e($trip['scheduled_date']) ?> <span class="text-secondary"><?= e(substr((string) $trip['scheduled_time'], 0, 5)) ?></span></td>
                            <td><?= e(trim($trip['student_first_name'] . ' ' . $trip['student_last_name'])) ?></td>
                            <td><?= e(trim($trip['driver_first_name'] . ' ' . $trip['driver_last_name'])) ?></td>
                            <td><span class="badge text-bg-primary text-capitalize"><?= e(str_replace('_', ' ', $trip['ride_status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recentTrips): ?><tr><td colspan="5" class="text-center text-secondary py-4">No trip records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const chart = document.querySelector('[data-finance-chart]');
    const empty = document.querySelector('[data-finance-empty]');
    const legend = document.querySelector('[data-finance-legend]');
    const updated = document.querySelector('[data-finance-updated]');

    if (!chart) {
        return;
    }

    const money = (value) => 'PHP ' + Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const bar = (type, value, max, label) => {
        const item = document.createElement('div');
        const height = Math.max(4, Math.round((Number(value || 0) / Math.max(1, max)) * 100));
        item.className = 'finance-bar finance-bar-' + type;
        item.style.height = height + '%';
        item.title = label + ' ' + money(value);
        return item;
    };

    const renderFinanceChart = (payload) => {
        const series = Array.isArray(payload.series) ? payload.series : [];
        chart.innerHTML = '';
        chart.classList.toggle('d-none', series.length === 0);
        if (empty) empty.classList.toggle('d-none', series.length > 0);
        if (legend) legend.classList.toggle('d-none', series.length === 0);
        if (updated && payload.generatedAt) updated.textContent = 'Updated ' + payload.generatedAt;

        series.forEach((row) => {
            const group = document.createElement('div');
            group.className = 'finance-chart-group';

            const bars = document.createElement('div');
            bars.className = 'finance-bars';
            bars.append(
                bar('payment', row.payments, payload.max, 'Payments'),
                bar('refund', row.refunds, payload.max, 'Refunds'),
                bar('payout', row.payouts, payload.max, 'Payouts')
            );

            const period = document.createElement('div');
            period.className = 'finance-period';
            period.textContent = row.label || row.period || '';

            const values = document.createElement('div');
            values.className = 'finance-values';
            [row.payments, row.refunds, row.payouts].forEach((value) => {
                const span = document.createElement('span');
                span.textContent = money(value);
                values.appendChild(span);
            });

            group.append(bars, period, values);
            chart.appendChild(group);
        });
    };

    const refreshFinanceChart = () => {
        fetch('reports.php?finance_series=1', { cache: 'no-store' })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload) renderFinanceChart(payload);
            })
            .catch(() => {});
    };

    window.setInterval(refreshFinanceChart, 15000);
})();
</script>

<?php admin_footer(); ?>
