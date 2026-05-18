<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$vehicles = $pdo->query(
    'SELECT drivers.id AS driver_id, drivers.vehicle_type, drivers.vehicle_plate_number, drivers.vehicle_model, drivers.vehicle_color,
        drivers.vehicle_photo_path, users.first_name, users.last_name,
        vehicles.id, vehicles.capacity, vehicles.registration_path, vehicles.status, vehicles.created_at
     FROM drivers
     JOIN users ON users.id = drivers.user_id
     LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
     ORDER BY COALESCE(vehicles.created_at, drivers.created_at) DESC, drivers.id DESC'
)->fetchAll();

admin_header('Vehicles', 'vehicles', 'Read-only vehicle registry sourced from driver registration and profile updates.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Vehicle Registry</h2><span class="text-secondary small"><?= count($vehicles) ?> total records</span></div>
        <span class="badge text-bg-light border">Admin view only</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Vehicle</th><th>Driver</th><th>Photo</th><th>ORCR</th><th>Capacity</th><th>Status</th><th class="text-end">Details</th></tr></thead>
                <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= e($vehicle['vehicle_plate_number']) ?>
                                <div class="text-secondary small"><?= e($vehicle['vehicle_model']) ?> • <?= e($vehicle['vehicle_color']) ?></div>
                            </td>
                            <td><?= e(trim($vehicle['first_name'] . ' ' . $vehicle['last_name'])) ?><div class="text-secondary small"><?= e($vehicle['vehicle_type']) ?></div></td>
                            <td>
                                <?php if (!empty($vehicle['registration_path']) && asset_url($vehicle['vehicle_photo_path'] ?? null)): ?>
                                    <img class="media-thumb" src="<?= e(asset_url($vehicle['vehicle_photo_path'])) ?>" alt="Vehicle photo">
                                <?php else: ?>
                                    <span class="text-secondary small">No photo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= render_document_link($vehicle['registration_path'] ?? ($vehicle['vehicle_photo_path'] ?? null), 'Open ORCR') ?></td>
                            <td><?= (int) ($vehicle['capacity'] ?: 1) ?></td>
                            <td><span class="badge text-bg-<?= ($vehicle['status'] ?? 'active') === 'active' ? 'success' : (($vehicle['status'] ?? '') === 'maintenance' ? 'warning' : 'secondary') ?>"><?= e($vehicle['status'] ?: 'active') ?></span></td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#vehicleDetails<?= (int) $vehicle['driver_id'] ?>"><i class="bi bi-eye"></i></button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$vehicles): ?><tr><td colspan="7" class="text-center text-secondary py-4">No vehicles yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($vehicles as $vehicle): ?>
    <div class="modal fade" id="vehicleDetails<?= (int) $vehicle['driver_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h3 class="modal-title h5">Vehicle Details</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="detail-grid mb-4">
                        <div class="detail-card"><h4>Driver</h4><p><?= e(trim($vehicle['first_name'] . ' ' . $vehicle['last_name'])) ?></p></div>
                        <div class="detail-card"><h4>Vehicle Type</h4><p><?= e($vehicle['vehicle_type']) ?></p></div>
                        <div class="detail-card"><h4>Plate Number</h4><p><?= e($vehicle['vehicle_plate_number']) ?></p></div>
                        <div class="detail-card"><h4>Model / Color</h4><p><?= e($vehicle['vehicle_model']) ?> / <?= e($vehicle['vehicle_color']) ?></p></div>
                        <div class="detail-card"><h4>Capacity</h4><p><?= (int) ($vehicle['capacity'] ?: 1) ?></p></div>
                        <div class="detail-card"><h4>Status</h4><p><?= e($vehicle['status'] ?: 'active') ?></p></div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h4 class="h6">Vehicle Photo</h4>
                            <?php if (!empty($vehicle['registration_path']) && asset_url($vehicle['vehicle_photo_path'] ?? null)): ?>
                                <img class="img-fluid rounded-4 border" src="<?= e(asset_url($vehicle['vehicle_photo_path'])) ?>" alt="Vehicle photo">
                            <?php else: ?>
                                <div class="text-secondary small">No vehicle photo uploaded.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h4 class="h6">Vehicle ORCR</h4>
                            <div class="mb-3"><?= render_document_link($vehicle['registration_path'] ?? ($vehicle['vehicle_photo_path'] ?? null), 'Open ORCR document') ?></div>
                            <div class="text-secondary small">The admin can review this document for verification, but vehicle records are not editable here.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>
