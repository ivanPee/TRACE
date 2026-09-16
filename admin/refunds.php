<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();
admin_ensure_monthly_plan_schema();

$money = fn ($value) => 'PHP ' . number_format((float) $value, 2);
$dateTime = fn ($value) => $value ? date('M d, Y h:i A', strtotime((string) $value)) : '-';
$monthLabel = fn ($value) => $value ? date('F Y', strtotime((string) $value)) : '-';
$validStatuses = ['pending', 'approved', 'paid', 'cancelled'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');
        $refundId = (int) post_value('refund_id');

        if ($action === 'update_refund' && $refundId > 0) {
            $status = strtolower(trim((string) post_value('status', 'approved')));
            $reference = trim((string) post_value('reference_number'));

            if (!in_array($status, $validStatuses, true)) {
                throw new RuntimeException('Invalid refund status.');
            }

            $stmt = $pdo->prepare('SELECT * FROM monthly_plan_refunds WHERE id = ? LIMIT 1');
            $stmt->execute([$refundId]);
            $refund = $stmt->fetch();

            if (!$refund) {
                throw new RuntimeException('Refund record not found.');
            }

            $refundedAtSql = $status === 'paid'
                ? 'COALESCE(refunded_at, NOW())'
                : ($status === 'cancelled' ? 'NULL' : 'refunded_at');

            $pdo->prepare(
                'UPDATE monthly_plan_refunds
                 SET status = ?, reference_number = NULLIF(?, ""), refunded_at = ' . $refundedAtSql . '
                 WHERE id = ?'
            )->execute([$status, $reference, $refundId]);

            admin_log('update', 'monthly_plan_refunds', $refundId, 'Admin updated refund status to ' . $status . '.');
            flash('success', 'Refund record updated.');
            redirect_to('refunds.php');
        }
    }
} catch (Exception $exception) {
    flash('error', $exception->getMessage());
    redirect_to('refunds.php');
}

$summary = $pdo->query(
    'SELECT
        COUNT(*) AS total_refunds,
        SUM(status = "pending") AS pending_refunds,
        SUM(status = "approved") AS approved_refunds,
        SUM(status = "paid") AS paid_refunds,
        SUM(status = "cancelled") AS cancelled_refunds,
        COALESCE(SUM(CASE WHEN status IN ("pending", "approved") THEN refund_amount ELSE 0 END), 0) AS outstanding_amount,
        COALESCE(SUM(CASE WHEN status IN ("approved", "paid") THEN refund_amount ELSE 0 END), 0) AS approved_amount,
        COALESCE(SUM(CASE WHEN status = "paid" THEN refund_amount ELSE 0 END), 0) AS paid_amount
     FROM monthly_plan_refunds'
)->fetch();

$refunds = $pdo->query(
    'SELECT monthly_plan_refunds.*,
        parent_users.first_name AS parent_first_name,
        parent_users.last_name AS parent_last_name,
        parent_users.email AS parent_email,
        parent_users.mobile_number AS parent_mobile_number,
        monthly_plans.billing_month,
        monthly_plans.trip_type,
        monthly_plan_routes.destination_address,
        bookings.booking_status,
        bookings.driver_payout_amount,
        COALESCE(refund_trip_stats.affected_trips, 0) AS affected_trips,
        COALESCE(refund_trip_stats.affected_payout_basis, monthly_plan_refunds.refund_amount * 2) AS affected_payout_basis
     FROM monthly_plan_refunds
     JOIN parents ON parents.id = monthly_plan_refunds.parent_id
     JOIN users parent_users ON parent_users.id = parents.user_id
     LEFT JOIN monthly_plans ON monthly_plans.id = monthly_plan_refunds.monthly_plan_id
     LEFT JOIN monthly_plan_routes ON monthly_plan_routes.id = monthly_plan_refunds.monthly_plan_route_id
     LEFT JOIN bookings ON bookings.id = monthly_plan_refunds.booking_id
     LEFT JOIN (
        SELECT parent_id, monthly_plan_id, monthly_plan_route_id, scheduled_date,
            COUNT(*) AS affected_trips,
            SUM(driver_payout_amount) AS affected_payout_basis
        FROM bookings
        WHERE monthly_plan_id IS NOT NULL
          AND monthly_plan_route_id IS NOT NULL
        GROUP BY parent_id, monthly_plan_id, monthly_plan_route_id, scheduled_date
     ) refund_trip_stats ON refund_trip_stats.parent_id = monthly_plan_refunds.parent_id
        AND refund_trip_stats.monthly_plan_id = monthly_plan_refunds.monthly_plan_id
        AND refund_trip_stats.monthly_plan_route_id = monthly_plan_refunds.monthly_plan_route_id
        AND refund_trip_stats.scheduled_date = monthly_plan_refunds.scheduled_date
     ORDER BY COALESCE(monthly_plan_refunds.refunded_at, monthly_plan_refunds.created_at) DESC, monthly_plan_refunds.id DESC'
)->fetchAll();

admin_header('Refunds', 'refunds', 'Review no-class monthly-plan refunds generated from cancelled daily service trips.');
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #078EFF">
            <div class="card-body"><div class="metric-label">Refund Records</div><div class="metric-value"><?= e((int) ($summary['total_refunds'] ?? 0)) ?></div><div class="metric-note"><?= e((int) ($summary['pending_refunds'] ?? 0)) ?> pending review</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #d99a00">
            <div class="card-body"><div class="metric-label">Approved</div><div class="metric-value fs-3"><?= e($money($summary['approved_amount'] ?? 0)) ?></div><div class="metric-note"><?= e((int) ($summary['approved_refunds'] ?? 0)) ?> approved records</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #18a957">
            <div class="card-body"><div class="metric-label">Paid Out</div><div class="metric-value fs-3"><?= e($money($summary['paid_amount'] ?? 0)) ?></div><div class="metric-note"><?= e((int) ($summary['paid_refunds'] ?? 0)) ?> completed refunds</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card metric-card h-100" style="--metric-color: #d64242">
            <div class="card-body"><div class="metric-label">Outstanding</div><div class="metric-value fs-3"><?= e($money($summary['outstanding_amount'] ?? 0)) ?></div><div class="metric-note"><?= e((int) ($summary['cancelled_refunds'] ?? 0)) ?> cancelled records</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="admin-card-title">Refund Ledger</h2>
            <div class="admin-card-subtitle"><?= e(count($refunds)) ?> generated no-class refund records</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="reports.php">Open reports</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Refund</th>
                        <th>Parent</th>
                        <th>Service Day</th>
                        <th>Route</th>
                        <th>Basis</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($refunds as $refund): ?>
                        <?php
                            $basis = (float) ($refund['affected_payout_basis'] ?? 0);
                            $expectedRefund = round($basis * 0.50, 2);
                        ?>
                        <tr>
                            <td>
                                <div class="record-title"><?= e($refund['reference_number'] ?: ('Refund #' . $refund['id'])) ?></div>
                                <div class="record-meta">Created <?= e($dateTime($refund['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="record-title"><?= e(trim($refund['parent_first_name'] . ' ' . $refund['parent_last_name'])) ?></div>
                                <div class="record-meta"><?= e($refund['parent_mobile_number'] ?: $refund['parent_email']) ?></div>
                            </td>
                            <td>
                                <div class="record-title"><?= e(date('M d, Y', strtotime((string) $refund['scheduled_date']))) ?></div>
                                <div class="record-meta"><?= e($monthLabel($refund['billing_month'])) ?></div>
                            </td>
                            <td style="max-width: 300px; white-space: normal;">
                                <div><?= e($refund['destination_address'] ?: 'Monthly plan route') ?></div>
                                <div class="record-meta"><?= e(str_replace('_', ' ', (string) ($refund['trip_type'] ?: 'monthly plan'))) ?></div>
                            </td>
                            <td>
                                <div><?= e((int) ($refund['affected_trips'] ?? 0)) ?> affected trip<?= (int) ($refund['affected_trips'] ?? 0) === 1 ? '' : 's' ?></div>
                                <div class="record-meta">50% of <?= e($money($basis)) ?><?= abs($expectedRefund - (float) $refund['refund_amount']) > 0.01 ? ' / check amount' : '' ?></div>
                            </td>
                            <td><?= admin_status_badge($refund['status']) ?></td>
                            <td class="text-end">
                                <div class="record-title"><?= e($money($refund['refund_amount'])) ?></div>
                                <div class="record-meta"><?= e($refund['refunded_at'] ? ('Paid ' . $dateTime($refund['refunded_at'])) : 'Not paid') ?></div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#refund<?= (int) $refund['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$refunds): ?><tr><td colspan="8" class="text-center text-secondary py-4">No refund records yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($refunds as $refund): ?>
    <div class="modal fade" id="refund<?= (int) $refund['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="post" data-confirm="Update this refund record?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_refund">
                <input type="hidden" name="refund_id" value="<?= (int) $refund['id'] ?>">
                <div class="modal-header">
                    <h3 class="modal-title h5">Refund #<?= e($refund['id']) ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="detail-grid mb-4">
                        <div class="detail-card"><h4>Parent</h4><p><?= e(trim($refund['parent_first_name'] . ' ' . $refund['parent_last_name'])) ?><br><?= e($refund['parent_mobile_number'] ?: $refund['parent_email']) ?></p></div>
                        <div class="detail-card"><h4>Service date</h4><p><?= e($refund['scheduled_date']) ?><br><?= e($monthLabel($refund['billing_month'])) ?></p></div>
                        <div class="detail-card"><h4>Amount</h4><p><?= e($money($refund['refund_amount'])) ?><br>50% no-class policy</p></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason</label>
                        <div class="form-control bg-light" style="min-height: 72px; white-space: pre-wrap;"><?= e($refund['reason'] ?: 'Cancelled daily trip') ?></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" name="status" required>
                                <?php foreach ($validStatuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= $refund['status'] === $status ? 'selected' : '' ?>><?= e(ucwords($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reference number</label>
                            <input class="form-control" name="reference_number" value="<?= e($refund['reference_number']) ?>" placeholder="Refund reference">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-primary" type="submit">Save refund</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>
