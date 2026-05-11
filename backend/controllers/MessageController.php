<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class MessageController extends ApiController
{
    public function send(): void
    {
        $user = $this->requireUser();
        $input = $this->input();
        $text = trim((string) ($input['text'] ?? ''));

        if ($text === '') {
            Response::json(['success' => false, 'message' => 'Message cannot be empty.'], 422);
        }

        $receiverId = $this->receiverForUser($user);

        if (!$receiverId) {
            Response::json(['success' => false, 'message' => 'No conversation target is available yet.'], 422);
        }

        $rideId = $this->latestRideIdForUser($user);
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (sender_user_id, receiver_user_id, ride_id, message_text)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(int) $user['id'], $receiverId, $rideId ?: null, $text]);
        $this->notifyUser($receiverId, 'New message', trim($user['first_name'] . ' sent you a message.'), 'message', (int) $this->pdo->lastInsertId());

        Response::json(['success' => true, 'message' => 'Message sent.']);
    }

    private function receiverForUser(array $user): ?int
    {
        if ($user['role_code'] === 'parent') {
            $stmt = $this->pdo->prepare(
                'SELECT du.id
                 FROM bookings
                 JOIN parents ON parents.id = bookings.parent_id
                 JOIN drivers ON drivers.id = bookings.assigned_driver_id
                 JOIN users du ON du.id = drivers.user_id
                 WHERE parents.user_id = ? AND bookings.assigned_driver_id IS NOT NULL
                 ORDER BY bookings.updated_at DESC
                 LIMIT 1'
            );
            $stmt->execute([(int) $user['id']]);
            $id = $stmt->fetchColumn();

            return $id ? (int) $id : null;
        }

        if ($user['role_code'] === 'driver') {
            $stmt = $this->pdo->prepare(
                'SELECT pu.id
                 FROM bookings
                 JOIN parents ON parents.id = bookings.parent_id
                 JOIN users pu ON pu.id = parents.user_id
                 JOIN drivers ON drivers.id = bookings.assigned_driver_id
                 WHERE drivers.user_id = ?
                 ORDER BY bookings.updated_at DESC
                 LIMIT 1'
            );
            $stmt->execute([(int) $user['id']]);
            $id = $stmt->fetchColumn();

            return $id ? (int) $id : null;
        }

        return null;
    }

    private function latestRideIdForUser(array $user): ?int
    {
        $join = $user['role_code'] === 'driver'
            ? 'JOIN drivers ON drivers.id = rides.driver_id AND drivers.user_id = ?'
            : 'JOIN bookings ON bookings.id = rides.booking_id JOIN parents ON parents.id = bookings.parent_id AND parents.user_id = ?';
        $stmt = $this->pdo->prepare(
            'SELECT rides.id
             FROM rides ' . $join . '
             ORDER BY rides.updated_at DESC
             LIMIT 1'
        );
        $stmt->execute([(int) $user['id']]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }
}
