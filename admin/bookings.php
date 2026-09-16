<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();
admin_ensure_monthly_plan_schema();

$money = fn ($value) => 'PHP ' . number_format((float) $value, 2);
$monthLabel = fn ($value) => $value ? date('F Y', strtotime((string) $value)) : '-';
$timeLabel = fn ($value) => $value ? substr((string) $value, 0, 5) : '-';
$timeValue = fn ($value) => $value ? substr((string) $value, 0, 5) : '';

function normalize_admin_time($value, $field)
{
    $value = trim((string) $value);

    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        throw new RuntimeException($field . ' must be a valid time.');
    }

    return $value . ':00';
}

function cancel_monthly_plan_admin(PDO $pdo, int $planId): void
{
    $pdo->prepare('UPDATE monthly_plans SET status = "cancelled" WHERE id = ?')->execute([$planId]);
    $pdo->prepare('UPDATE service_payments SET status = "cancelled" WHERE monthly_plan_id = ? AND status <> "paid"')->execute([$planId]);
    $pdo->prepare(
        'UPDATE rides
         JOIN bookings ON bookings.id = rides.booking_id
         SET rides.ride_status = "cancelled", rides.cancelled_at = COALESCE(rides.cancelled_at, NOW())
         WHERE bookings.monthly_plan_id = ?
           AND rides.ride_status IN ("assigned", "driver_arriving", "arrived")'
    )->execute([$planId]);
    $pdo->prepare(
        'UPDATE bookings
         LEFT JOIN rides ON rides.booking_id = bookings.id
         SET bookings.booking_status = "cancelled"
         WHERE bookings.monthly_plan_id = ?
           AND bookings.booking_status NOT IN ("completed", "cancelled")
           AND (rides.id IS NULL OR rides.ride_status = "cancelled")'
    )->execute([$planId]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');
        $planId = (int) post_value('plan_id');

        if ($action === 'cancel' && $planId > 0) {
            $pdo->beginTransaction();
            cancel_monthly_plan_admin($pdo, $planId);
            $pdo->commit();

            flash('success', 'Monthly plan subscription cancelled.');
            redirect_to('bookings.php');
        }

        if ($action === 'update_plan' && $planId > 0) {
            $scheduledTime = normalize_admin_time(post_value('scheduled_time'), 'Pickup time');
            $returnTime = normalize_admin_time(post_value('return_time'), 'Return/drop-off time');
            $status = (string) post_value('status', 'active');

            if (!in_array($status, ['active', 'cancelled'], true)) {
                throw new RuntimeException('Invalid monthly plan status.');
            }

            $planStmt = $pdo->prepare(
                'SELECT monthly_plans.*,
                    latest_payment.status AS payment_status
                 FROM monthly_plans
                 LEFT JOIN service_payments latest_payment ON latest_payment.id = (
                    SELECT service_payments.id
                    FROM service_payments
                    WHERE service_payments.monthly_plan_id = monthly_plans.id
                    ORDER BY service_payments.paid_at DESC, service_payments.id DESC
                    LIMIT 1
                 )
                 WHERE monthly_plans.id = ?
                 LIMIT 1'
            );
            $planStmt->execute([$planId]);
            $plan = $planStmt->fetch();

            if (!$plan) {
                throw new RuntimeException('Monthly plan not found.');
            }

            if (($plan['payment_status'] ?? '') !== 'paid') {
                throw new RuntimeException('Only paid monthly plans can be edited by admin.');
            }

            if (($plan['status'] ?? '') === 'cancelled' && $status === 'active') {
                throw new RuntimeException('Cancelled monthly plans cannot be reactivated from admin.');
            }

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE monthly_plans SET scheduled_time = ?, return_time = ?, status = ? WHERE id = ?')->execute([$scheduledTime, $returnTime, $status, $planId]);

            if ($status === 'cancelled') {
                cancel_monthly_plan_admin($pdo, $planId);
            } else {
                $pdo->prepare(
                    'UPDATE bookings
                     SET scheduled_time = ?, pickup_time = ?, dropoff_time = ?, adjusted_pickup_time = NULL
                     WHERE monthly_plan_id = ?
                       AND booking_status NOT IN ("completed", "cancelled")
                       AND LOWER(COALESCE(notes, "")) NOT LIKE "%return%"'
                )->execute([$scheduledTime, $scheduledTime, $returnTime, $planId]);
                $pdo->prepare(
                    'UPDATE bookings
                     SET scheduled_time = ?, pickup_time = ?, dropoff_time = NULL, adjusted_pickup_time = NULL
                     WHERE monthly_plan_id = ?
                       AND booking_status NOT IN ("completed", "cancelled")
                       AND LOWER(COALESCE(notes, "")) LIKE "%return%"'
                )->execute([$returnTime, $returnTime, $planId]);
            }

            admin_log('update', 'monthly_plans', $planId, 'Admin updated paid monthly plan timing/status.');
            $pdo->commit();

            flash('success', 'Paid monthly plan updated.');
            redirect_to('bookings.php');
        }
    }
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('error', $exception->getMessage());
    redirect_to('bookings.php');
}

$summary = $pdo->query(
    'SELECT
        COUNT(*) AS total_plans,
        SUM(monthly_plans.status = "active") AS active_plans,
        SUM(monthly_plans.status = "cancelled") AS cancelled_plans,
        COALESCE(SUM(monthly_plans.monthly_amount), 0) AS gross_monthly_amount,
        COALESCE(SUM(CASE WHEN monthly_plans.status = "active" THEN monthly_plans.monthly_amount ELSE 0 END), 0) AS active_monthly_amount,
        COALESCE(SUM(monthly_plans.child_count), 0) AS covered_children,
        COALESCE(SUM(monthly_plans.route_count), 0) AS route_count
     FROM monthly_plans'
)->fetch();

$plans = $pdo->query(
    'SELECT monthly_plans.*,
        parent_users.first_name AS parent_first_name,
        parent_users.last_name AS parent_last_name,
        parent_users.email AS parent_email,
        parent_users.mobile_number AS parent_mobile_number,
        latest_payment.id AS payment_id,
        latest_payment.amount_due,
        latest_payment.amount_paid,
        latest_payment.payment_method,
        latest_payment.reference_number,
        latest_payment.status AS payment_status,
        latest_payment.paid_at,
        booking_stats.generated_trips,
        booking_stats.completed_trips,
        booking_stats.cancelled_trips,
        booking_stats.open_trips,
        child_stats.child_names,
        route_stats.route_destinations
     FROM monthly_plans
     JOIN parents ON parents.id = monthly_plans.parent_id
     JOIN users parent_users ON parent_users.id = parents.user_id
     LEFT JOIN service_payments latest_payment ON latest_payment.id = (
        SELECT service_payments.id
        FROM service_payments
        WHERE service_payments.monthly_plan_id = monthly_plans.id
        ORDER BY service_payments.paid_at DESC, service_payments.id DESC
        LIMIT 1
     )
     LEFT JOIN (
        SELECT monthly_plan_id,
            COUNT(*) AS generated_trips,
            SUM(booking_status = "completed") AS completed_trips,
            SUM(booking_status = "cancelled") AS cancelled_trips,
            SUM(booking_status NOT IN ("completed", "cancelled")) AS open_trips
        FROM bookings
        WHERE monthly_plan_id IS NOT NULL
        GROUP BY monthly_plan_id
     ) booking_stats ON booking_stats.monthly_plan_id = monthly_plans.id
     LEFT JOIN (
        SELECT monthly_plan_routes.monthly_plan_id,
            GROUP_CONCAT(DISTINCT TRIM(CONCAT(child_users.first_name, " ", child_users.last_name)) ORDER BY child_users.first_name SEPARATOR ", ") AS child_names
        FROM monthly_plan_routes
        JOIN monthly_plan_route_students ON monthly_plan_route_students.monthly_plan_route_id = monthly_plan_routes.id
        JOIN students ON students.id = monthly_plan_route_students.student_id
        JOIN users child_users ON child_users.id = students.user_id
        GROUP BY monthly_plan_routes.monthly_plan_id
     ) child_stats ON child_stats.monthly_plan_id = monthly_plans.id
     LEFT JOIN (
        SELECT monthly_plan_id,
            GROUP_CONCAT(DISTINCT destination_address ORDER BY id SEPARATOR " | ") AS route_destinations
        FROM monthly_plan_routes
        GROUP BY monthly_plan_id
     ) route_stats ON route_stats.monthly_plan_id = monthly_plans.id
     ORDER BY monthly_plans.billing_month DESC, monthly_plans.created_at DESC, monthly_plans.id DESC'
)->fetchAll();

$routeStmt = $pdo->prepare(
    'SELECT monthly_plan_routes.*,
        GROUP_CONCAT(TRIM(CONCAT(child_users.first_name, " ", child_users.last_name)) ORDER BY child_users.first_name SEPARATOR ", ") AS child_names,
        GROUP_CONCAT(CONCAT(TRIM(CONCAT(child_users.first_name, " ", child_users.last_name)), " - ", monthly_plan_route_students.pickup_address) ORDER BY child_users.first_name SEPARATOR "\n") AS pickup_summary
     FROM monthly_plan_routes
     LEFT JOIN monthly_plan_route_students ON monthly_plan_route_students.monthly_plan_route_id = monthly_plan_routes.id
     LEFT JOIN students ON students.id = monthly_plan_route_students.student_id
     LEFT JOIN users child_users ON child_users.id = students.user_id
     WHERE monthly_plan_routes.monthly_plan_id = ?
     GROUP BY monthly_plan_routes.id
     ORDER BY monthly_plan_routes.id ASC'
);

admin_header('Monthly Plan Subscriptions', 'bookings', 'Monitor paid monthly subscriptions, route coverage, children, generated trips, and payment accuracy.');
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #078EFF">
            <div class="card-body"><div class="metric-label">Subscriptions</div><div class="metric-value"><?= e((int) ($summary['total_plans'] ?? 0)) ?></div><div class="metric-note"><?= e((int) ($summary['active_plans'] ?? 0)) ?> active plans</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #18a957">
            <div class="card-body"><div class="metric-label">Active Monthly Value</div><div class="metric-value fs-3"><?= e($money($summary['active_monthly_amount'] ?? 0)) ?></div><div class="metric-note"><?= e($money($summary['gross_monthly_amount'] ?? 0)) ?> total recorded</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #d99a00">
            <div class="card-body"><div class="metric-label">Covered Children</div><div class="metric-value"><?= e((int) ($summary['covered_children'] ?? 0)) ?></div><div class="metric-note"><?= e((int) ($summary['route_count'] ?? 0)) ?> subscription routes</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #d64242">
            <div class="card-body"><div class="metric-label">Cancelled</div><div class="metric-value"><?= e((int) ($summary['cancelled_plans'] ?? 0)) ?></div><div class="metric-note">Plans stopped by parent/admin</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="admin-card-title">Subscription Ledger</h2>
            <div class="admin-card-subtitle"><?= count($plans) ?> monthly plan records from parent payments</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="reports.php">Open reports</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Parent</th>
                        <th>Coverage</th>
                        <th>Billing</th>
                        <th>Payment</th>
                        <th>Generated Trips</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                            $generatedTrips = (int) ($plan['generated_trips'] ?? 0);
                            $completedTrips = (int) ($plan['completed_trips'] ?? 0);
                            $openTrips = (int) ($plan['open_trips'] ?? 0);
                            $cancelledTrips = (int) ($plan['cancelled_trips'] ?? 0);
                            $paymentStatus = $plan['payment_status'] ?: 'unpaid';
                        ?>
                        <tr>
                            <td>
                                <div class="record-title">Plan #<?= e($plan['id']) ?> &middot; <?= e($monthLabel($plan['billing_month'])) ?></div>
                                <div class="record-meta"><?= e(str_replace('_', ' ', $plan['trip_type'])) ?> &middot; <?= e($timeLabel($plan['scheduled_time'])) ?><?= $plan['return_time'] ? e(' / ' . $timeLabel($plan['return_time'])) : '' ?></div>
                            </td>
                            <td>
                                <div class="record-title"><?= e(trim($plan['parent_first_name'] . ' ' . $plan['parent_last_name'])) ?></div>
                                <div class="record-meta"><?= e($plan['parent_mobile_number'] ?: $plan['parent_email']) ?></div>
                            </td>
                            <td>
                                <div><?= e((int) $plan['child_count']) ?> children &middot; <?= e((int) $plan['route_count']) ?> routes</div>
                                <div class="record-meta text-truncate" style="max-width: 260px;"><?= e($plan['child_names'] ?: 'No children linked') ?></div>
                            </td>
                            <td>
                                <div class="record-title"><?= e($money($plan['monthly_amount'])) ?></div>
                                <div class="record-meta"><?= e((int) $plan['service_days']) ?> service days &middot; daily <?= e($money($plan['net_daily_total'])) ?></div>
                            </td>
                            <td>
                                <?= admin_status_badge($paymentStatus) ?>
                                <div class="record-meta"><?= e($plan['reference_number'] ?: 'No reference') ?> &middot; <?= e($plan['payment_method'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="record-title"><?= e($generatedTrips) ?> total</div>
                                <div class="record-meta"><?= e($completedTrips) ?> completed &middot; <?= e($openTrips) ?> open &middot; <?= e($cancelledTrips) ?> cancelled</div>
                            </td>
                            <td><?= admin_status_badge($plan['status']) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#viewPlan<?= (int) $plan['id'] ?>"><i class="bi bi-eye"></i></button>
                                <?php if ($paymentStatus === 'paid' && $plan['status'] !== 'cancelled'): ?>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editPlan<?= (int) $plan['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <?php endif; ?>
                                <?php if ($plan['status'] !== 'cancelled'): ?>
                                    <form class="d-inline" method="post" data-confirm="Cancel this monthly plan subscription and unstarted generated trips?">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$plans): ?><tr><td colspan="8" class="text-center text-secondary py-4">No monthly plan subscriptions yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($plans as $plan): ?>
    <?php $routeStmt->execute([(int) $plan['id']]); $routes = $routeStmt->fetchAll(); ?>
    <div class="modal fade" id="viewPlan<?= (int) $plan['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5">Monthly Plan #<?= e($plan['id']) ?> &middot; <?= e($monthLabel($plan['billing_month'])) ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="detail-grid mb-4">
                        <div class="detail-card"><h4>Parent</h4><p><?= e(trim($plan['parent_first_name'] . ' ' . $plan['parent_last_name'])) ?><br><?= e($plan['parent_mobile_number']) ?></p></div>
                        <div class="detail-card"><h4>Payment</h4><p><?= e($money($plan['amount_paid'] ?? $plan['monthly_amount'])) ?><br><?= e($plan['reference_number'] ?: 'No reference') ?></p></div>
                        <div class="detail-card"><h4>Service</h4><p><?= e((int) $plan['service_days']) ?> days<br><?= e($timeLabel($plan['scheduled_time'])) ?><?= $plan['return_time'] ? e(' / ' . $timeLabel($plan['return_time'])) : '' ?></p></div>
                        <div class="detail-card"><h4>Generated Trips</h4><p><?= e((int) ($plan['generated_trips'] ?? 0)) ?> total<br><?= e((int) ($plan['open_trips'] ?? 0)) ?> open</p></div>
                    </div>

                    <h4 class="h6 mb-3">Subscription Routes</h4>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Destination</th><th>Children</th><th>Daily Amount</th><th>Discount</th><th>Pickup Details</th></tr></thead>
                            <tbody>
                                <?php foreach ($routes as $route): ?>
                                    <tr>
                                        <td style="max-width: 320px; white-space: normal;"><?= e($route['destination_address']) ?></td>
                                        <td><?= e($route['child_names'] ?: '-') ?></td>
                                        <td><?= e($money($route['net_daily_amount'])) ?></td>
                                        <td><?= e($money($route['discount_amount'])) ?></td>
                                        <td style="max-width: 360px; white-space: pre-line;"><?= e($route['pickup_summary'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$routes): ?><tr><td colspan="5" class="text-center text-secondary py-4">No route details recorded.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
    <?php if (($plan['payment_status'] ?: 'unpaid') === 'paid' && $plan['status'] !== 'cancelled'): ?>
        <div class="modal fade" id="editPlan<?= (int) $plan['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" method="post" data-confirm="Apply these admin changes to this paid monthly plan and its open generated trips?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_plan">
                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                    <div class="modal-header">
                        <h3 class="modal-title h5">Edit Paid Monthly Plan #<?= e($plan['id']) ?></h3>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            Changes update this paid plan and reschedule open generated trips. Completed and cancelled trips are kept unchanged.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pickup time</label>
                                <input class="form-control" type="time" name="scheduled_time" value="<?= e($timeValue($plan['scheduled_time'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Return/drop-off time</label>
                                <input class="form-control" type="time" name="return_time" value="<?= e($timeValue($plan['return_time'] ?: $plan['scheduled_time'])) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Plan status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?= $plan['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="cancelled" <?= $plan['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment</label>
                                <div class="form-control bg-light"><?= e($money($plan['amount_paid'] ?? $plan['monthly_amount'])) ?> paid</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button class="btn btn-primary" type="submit">Save plan changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php admin_footer(); ?>
