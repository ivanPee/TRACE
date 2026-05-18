<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');

        if ($action === 'create') {
            $pdo->beginTransaction();
            $userId = create_user('driver', $_POST + ['status' => post_value('status', 'pending')]);
            $stmt = $pdo->prepare(
                'INSERT INTO drivers (user_id, license_number, license_expiry, license_photo_path, license_verified, vehicle_type, vehicle_plate_number, vehicle_model, vehicle_color, vehicle_photo_path, approval_status, is_online)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                post_value('license_number'),
                post_value('license_expiry'),
                post_value('license_photo_path', 'pending-upload'),
                !empty($_POST['license_verified']) ? 1 : 0,
                post_value('vehicle_type'),
                post_value('vehicle_plate_number'),
                post_value('vehicle_model'),
                post_value('vehicle_color'),
                post_value('vehicle_photo_path') ?: null,
                post_value('approval_status', 'pending'),
                !empty($_POST['is_online']) ? 1 : 0,
            ]);
            $driverId = (int) $pdo->lastInsertId();
            sync_vehicle_record_admin($driverId, $_POST + ['registration_path' => post_value('registration_path') ?: null, 'vehicle_status' => 'active']);
            $pdo->commit();
            flash('success', 'Rider created successfully.');
            redirect_to('riders.php');
        }

        if ($action === 'update') {
            $pdo->beginTransaction();
            update_user((int) post_value('user_id'), $_POST + ['role_code' => 'driver']);
            $stmt = $pdo->prepare(
                'UPDATE drivers SET license_number = ?, license_expiry = ?, license_photo_path = ?, license_verified = ?, vehicle_type = ?, vehicle_plate_number = ?, vehicle_model = ?, vehicle_color = ?, vehicle_photo_path = ?, approval_status = ?, is_online = ? WHERE id = ?'
            );
            $stmt->execute([
                post_value('license_number'),
                post_value('license_expiry'),
                post_value('license_photo_path', 'pending-upload'),
                !empty($_POST['license_verified']) ? 1 : 0,
                post_value('vehicle_type'),
                post_value('vehicle_plate_number'),
                post_value('vehicle_model'),
                post_value('vehicle_color'),
                post_value('vehicle_photo_path') ?: null,
                post_value('approval_status', 'pending'),
                !empty($_POST['is_online']) ? 1 : 0,
                (int) post_value('driver_id'),
            ]);
            sync_vehicle_record_admin((int) post_value('driver_id'), $_POST + ['registration_path' => post_value('registration_path') ?: null, 'vehicle_status' => 'active']);
            $pdo->commit();
            flash('success', 'Rider updated successfully.');
            redirect_to('riders.php');
        }

        if ($action === 'delete') {
            $pdo->beginTransaction();
            $driverId = (int) post_value('driver_id');
            $userId = (int) post_value('user_id');
            $pdo->prepare('DELETE FROM vehicles WHERE driver_id = ?')->execute([$driverId]);
            $pdo->prepare('DELETE FROM drivers WHERE id = ?')->execute([$driverId]);
            delete_user_tree($userId);
            $pdo->commit();
            flash('success', 'Rider deleted successfully.');
            redirect_to('riders.php');
        }
    }
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
    redirect_to('riders.php');
}

$riders = $pdo->query(
    'SELECT drivers.*, users.first_name, users.middle_name, users.last_name, users.email, users.mobile_number, users.status, users.is_verified, users.profile_photo,
        vehicles.registration_path, vehicles.capacity, vehicles.status AS vehicle_status
     FROM drivers
     JOIN users ON users.id = drivers.user_id
     LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
     ORDER BY drivers.created_at DESC, drivers.id DESC'
)->fetchAll();
$roles = $pdo->query('SELECT * FROM roles ORDER BY name')->fetchAll();

admin_header('Riders', 'riders', 'Approve rider accounts, manage licenses, and maintain vehicle profile details.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2 class="h6 mb-0">Rider Directory</h2>
            <span class="text-secondary small"><?= count($riders) ?> total records</span>
        </div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createRiderModal">
            <i class="bi bi-plus-lg me-1"></i>Add rider
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Rider</th>
                        <th>Profile</th>
                        <th>Contact</th>
                        <th>License</th>
                        <th>Vehicle</th>
                        <th>Documents</th>
                        <th>Approval</th>
                        <th>Online</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riders as $rider): ?>
                        <tr>
                            <td class="fw-semibold"><?= e(full_name($rider)) ?></td>
                            <td>
                                <?php if (asset_url($rider['profile_photo'] ?? null)): ?>
                                    <img class="media-thumb" src="<?= e(asset_url($rider['profile_photo'])) ?>" alt="<?= e(full_name($rider)) ?>">
                                <?php else: ?>
                                    <span class="text-secondary small">No photo</span>
                                <?php endif; ?>
                            </td>
                            <td><div><?= e($rider['email']) ?></div><span class="text-secondary small"><?= e($rider['mobile_number']) ?></span></td>
                            <td><?= e($rider['license_number']) ?><div class="text-secondary small">Exp. <?= e($rider['license_expiry']) ?></div></td>
                            <td><?= e($rider['vehicle_plate_number']) ?><div class="text-secondary small"><?= e($rider['vehicle_model']) ?></div></td>
                            <td class="d-flex gap-2">
                                <?= render_document_link($rider['license_photo_path'] ?? null, 'License') ?>
                                <?= render_document_link(!empty($rider['registration_path']) ? ($rider['vehicle_photo_path'] ?? null) : null, 'Vehicle') ?>
                                <?= render_document_link($rider['registration_path'] ?? ($rider['vehicle_photo_path'] ?? null), 'ORCR') ?>
                            </td>
                            <td><span class="badge text-bg-<?= $rider['approval_status'] === 'approved' ? 'success' : ($rider['approval_status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e($rider['approval_status']) ?></span></td>
                            <td><?= (int) $rider['is_online'] === 1 ? '<span class="badge text-bg-success">Online</span>' : '<span class="badge text-bg-light border">Offline</span>' ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#viewRider<?= (int) $rider['id'] ?>"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editRider<?= (int) $rider['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this rider and assigned vehicle records?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="driver_id" value="<?= (int) $rider['id'] ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $rider['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$riders): ?>
                        <tr><td colspan="9" class="text-center text-secondary py-4">No riders yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($riders as $rider): ?>
    <div class="modal fade" id="viewRider<?= (int) $rider['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h3 class="modal-title h5">Rider Verification Details</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="detail-grid mb-4">
                        <div class="detail-card"><h4>Rider</h4><p><?= e(full_name($rider)) ?></p></div>
                        <div class="detail-card"><h4>Contact</h4><p><?= e($rider['mobile_number']) ?><br><?= e($rider['email']) ?></p></div>
                        <div class="detail-card"><h4>License</h4><p><?= e($rider['license_number']) ?><br>Exp. <?= e($rider['license_expiry']) ?></p></div>
                        <div class="detail-card"><h4>Vehicle</h4><p><?= e($rider['vehicle_type']) ?><br><?= e($rider['vehicle_model']) ?> | <?= e($rider['vehicle_plate_number']) ?></p></div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h4 class="h6">Profile Photo</h4>
                            <?php if (asset_url($rider['profile_photo'] ?? null)): ?>
                                <img class="img-fluid rounded-4 border" src="<?= e(asset_url($rider['profile_photo'])) ?>" alt="<?= e(full_name($rider)) ?>">
                            <?php else: ?>
                                <div class="text-secondary small">No profile photo uploaded.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <h4 class="h6">Driver License</h4>
                            <?php if (asset_url($rider['license_photo_path'] ?? null)): ?>
                                <img class="img-fluid rounded-4 border" src="<?= e(asset_url($rider['license_photo_path'])) ?>" alt="Driver license">
                            <?php else: ?>
                                <div class="text-secondary small">No license image uploaded.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <h4 class="h6">Vehicle Photo</h4>
                            <?php if (!empty($rider['registration_path']) && asset_url($rider['vehicle_photo_path'] ?? null)): ?>
                                <img class="img-fluid rounded-4 border" src="<?= e(asset_url($rider['vehicle_photo_path'])) ?>" alt="Vehicle photo">
                            <?php else: ?>
                                <div class="text-secondary small">No vehicle photo uploaded.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <h4 class="h6">Vehicle ORCR</h4>
                            <?= render_document_link($rider['registration_path'] ?? ($rider['vehicle_photo_path'] ?? null), 'Open ORCR document') ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php $record = ['status' => 'pending', 'approval_status' => 'pending']; ?>
<div class="modal fade" id="createRiderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="modal-header"><h3 class="modal-title h5">Add Rider</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><?php include __DIR__ . '/partials/rider_fields.php'; ?></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save rider</button></div>
        </form>
    </div>
</div>

<?php foreach ($riders as $record): ?>
    <div class="modal fade" id="editRider<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content" method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="driver_id" value="<?= (int) $record['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $record['user_id'] ?>">
                <div class="modal-header"><h3 class="modal-title h5">Edit Rider</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><?php include __DIR__ . '/partials/rider_fields.php'; ?></div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update rider</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php admin_footer(); ?>
