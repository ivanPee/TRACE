<?php

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $action = (string) post_value('action');
        $rideId = post_value('ride_id') !== '' ? (int) post_value('ride_id') : null;
        $params = [(int) post_value('sender_user_id'), (int) post_value('receiver_user_id'), $rideId, post_value('message_text'), post_value('message_type', 'text'), !empty($_POST['is_read']) ? 1 : 0];

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO messages (sender_user_id, receiver_user_id, ride_id, message_text, message_type, is_read) VALUES (?, ?, ?, ?, ?, ?)')->execute($params);
            flash('success', 'Message created successfully.');
            redirect_to('messages.php');
        }

        if ($action === 'update') {
            $params[] = (int) post_value('message_id');
            $pdo->prepare('UPDATE messages SET sender_user_id = ?, receiver_user_id = ?, ride_id = ?, message_text = ?, message_type = ?, is_read = ? WHERE id = ?')->execute($params);
            flash('success', 'Message updated successfully.');
            redirect_to('messages.php');
        }

        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([(int) post_value('message_id')]);
            flash('success', 'Message deleted successfully.');
            redirect_to('messages.php');
        }
    }
} catch (Exception $exception) {
    flash('error', $exception->getMessage());
    redirect_to('messages.php');
}

$users = $pdo->query('SELECT id, first_name, last_name, email FROM users ORDER BY last_name, first_name')->fetchAll();
$rides = $pdo->query('SELECT id, ride_status FROM rides ORDER BY id DESC')->fetchAll();
$messages = $pdo->query(
    'SELECT messages.*, sender.first_name AS sender_first_name, sender.last_name AS sender_last_name,
        receiver.first_name AS receiver_first_name, receiver.last_name AS receiver_last_name
     FROM messages
     JOIN users sender ON sender.id = messages.sender_user_id
     JOIN users receiver ON receiver.id = messages.receiver_user_id
     ORDER BY messages.created_at DESC, messages.id DESC'
)->fetchAll();

admin_header('Messages', 'messages', 'Create, review, update, and remove user communication records.');
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><h2 class="h6 mb-0">Messages</h2><span class="text-secondary small"><?= count($messages) ?> total records</span></div>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createMessageModal" <?= count($users) < 2 ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1"></i>Add message</button>
    </div>
    <div class="card-body">
        <?php if (count($users) < 2): ?><div class="alert alert-warning">Create at least two users before adding messages.</div><?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Sender</th><th>Receiver</th><th>Type</th><th>Message</th><th>Read</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <tr>
                            <td><?= e(trim($message['sender_first_name'] . ' ' . $message['sender_last_name'])) ?></td>
                            <td><?= e(trim($message['receiver_first_name'] . ' ' . $message['receiver_last_name'])) ?></td>
                            <td><span class="badge text-bg-light border"><?= e($message['message_type']) ?></span></td>
                            <td class="text-secondary"><?= e(text_excerpt((string) $message['message_text'])) ?></td>
                            <td><?= (int) $message['is_read'] === 1 ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning">No</span>' ?></td>
                            <td><?= e($message['created_at']) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editMessage<?= (int) $message['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form class="d-inline" method="post" data-confirm="Delete this message?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?><tr><td colspan="7" class="text-center text-secondary py-4">No messages yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $record = ['message_type' => 'text']; ?>
<div class="modal fade" id="createMessageModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="modal-header"><h3 class="modal-title h5">Add Message</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><?php include __DIR__ . '/partials/message_fields.php'; ?></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save message</button></div>
</form></div></div>

<?php foreach ($messages as $record): ?>
    <div class="modal fade" id="editMessage<?= (int) $record['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="message_id" value="<?= (int) $record['id'] ?>">
        <div class="modal-header"><h3 class="modal-title h5">Edit Message</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><?php include __DIR__ . '/partials/message_fields.php'; ?></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update message</button></div>
    </form></div></div>
<?php endforeach; ?>

<?php admin_footer(); ?>
