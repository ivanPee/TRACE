<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');
        $params = [(int) post_value('driver_id'), post_value('plate_number'), post_value('model'), post_value('color'), (int) post_value('capacity', 1), post_value('registration_path') ?: null, post_value('status', 'active')];

        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO vehicles (driver_id, plate_number, model, color, capacity, registration_path, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute($params);
            flash('success', 'Vehicle created successfully.');
            redirect_to('vehicles.php');
        }

        if ($action === 'update') {
            $params[] = (int) post_value('vehicle_id');
            $stmt = $pdo->prepare('UPDATE vehicles SET driver_id = ?, plate_number = ?, model = ?, color = ?, capacity = ?, registration_path = ?, status = ? WHERE id = ?');
            $stmt->execute($params);
            flash('success', 'Vehicle updated successfully.');
            redirect_to('vehicles.php');
        }

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM vehicles WHERE id = ?')->execute([(int) post_value('vehicle_id')]);
            flash('success', 'Vehicle deleted successfully.');
            redirect_to('vehicles.php');
        }
    }
} catch (Exception $exception) {
    flash('error', $exception->getMessage());
    redirect_to('vehicles.php');
}

$drivers = $pdo->query(
    'SELECT drivers.id, users.first_name, users.last_name, drivers.vehicle_plate_number
     FROM drivers
     JOIN users ON users.id = drivers.user_id
     ORDER BY users.last_name, users.first_name'
)->fetchAll();
$vehicles = $pdo->query(
    'SELECT vehicles.*, users.first_name, users.last_name
     FROM vehicles
     JOIN drivers ON drivers.id = vehicles.driver_id
     JOIN users ON users.id = drivers.user_id
     ORDER BY vehicles.created_at DESC, vehicles.id DESC'
)->fetchAll();

admin_header('Vehicles', 'vehicles', 'Maintain fleet records, capacity, registration paths, and status.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Fleet Records</h2><span class="text-secondary small"><?= count($vehicles) ?> total records</span></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createVehicleModal" <?= !$drivers ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Add vehicle</button>
    </div>
    <div class="card-body">
        <?php if (!$drivers): ?><div class="alert alert-warning">Create at least one rider before adding fleet vehicles.</div><?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Plate</th><th>Driver</th><th>Model</th><th>Color</th><th>Capacity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($vehicle['plate_number']) ?></td>
                            <td><?= e(trim($vehicle['first_name'] . ' ' . $vehicle['last_name'])) ?></td>
                            <td><?= e($vehicle['model']) ?></td>
                            <td><?= e($vehicle['color']) ?></td>
                            <td><?= (int) $vehicle['capacity'] ?></td>
                            <td><span class="badge text-bg-<?= $vehicle['status'] === 'active' ? 'success' : ($vehicle['status'] === 'maintenance' ? 'warning' : 'secondary') ?>"><?= e($vehicle['status']) ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editVehicle<?= (int) $vehicle['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this vehicle record?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="vehicle_id" value="<?= (int) $vehicle['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$vehicles): ?><tr><td colspan="7" class="text-center text-secondary py-4">No vehicles yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $record = ['status' => 'active', 'capacity' => 1]; ?>
<div class="modal fade" id="createVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="modal-header"><h3 class="modal-title h5">Add Vehicle</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><?php include __DIR__ . '/partials/vehicle_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save vehicle</button></div>
    </form></div>
</div>

<?php foreach ($vehicles as $record): ?>
    <div class="modal fade" id="editVehicle<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><form class="modal-content" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="vehicle_id" value="<?= (int) $record['id'] ?>">
            <div class="modal-header"><h3 class="modal-title h5">Edit Vehicle</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><?php include __DIR__ . '/partials/vehicle_fields.php'; ?></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update vehicle</button></div>
        </form></div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>
