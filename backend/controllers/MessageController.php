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

        $receiverId = (int) ($input['receiver_user_id'] ?? $input['receiverUserId'] ?? 0);
        $rideId = (int) ($input['ride_id'] ?? $input['rideId'] ?? 0);

        if ($receiverId && !$this->canMessageUser($user, $receiverId, $rideId ?: null)) {
            Response::json(['success' => false, 'message' => 'You cannot message that user for this trip.'], 403);
        }

        if (!$receiverId) {
            $receiverId = $this->receiverForUser($user);
        }

        if (!$receiverId) {
            Response::json(['success' => false, 'message' => 'No conversation target is available yet.'], 422);
        }

        $rideId = $rideId ?: $this->latestRideIdForUser($user);
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

        if ($user['role_code'] === 'student') {
            $stmt = $this->pdo->prepare(
                'SELECT pu.id
                 FROM students
                 JOIN parents ON parents.id = students.parent_id
                 JOIN users pu ON pu.id = parents.user_id
                 WHERE students.user_id = ?
                 LIMIT 1'
            );
            $stmt->execute([(int) $user['id']]);
            $id = $stmt->fetchColumn();

            return $id ? (int) $id : null;
        }

        return null;
    }

    private function canMessageUser(array $user, int $receiverId, ?int $rideId): bool
    {
        if ((int) $user['id'] === $receiverId) {
            return false;
        }

        if ($rideId) {
            $stmt = $this->pdo->prepare(
                'SELECT 1
                 FROM rides
                 JOIN bookings ON bookings.id = rides.booking_id
                 JOIN parents ON parents.id = bookings.parent_id
                 JOIN students ON students.id = bookings.student_id
                 JOIN drivers ON drivers.id = rides.driver_id
                 WHERE rides.id = ?
                   AND ? IN (parents.user_id, students.user_id, drivers.user_id)
                   AND ? IN (parents.user_id, students.user_id, drivers.user_id)
                 LIMIT 1'
            );
            $stmt->execute([$rideId, (int) $user['id'], $receiverId]);

            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        $conversation = $this->pdo->prepare(
            'SELECT 1 FROM messages
             WHERE (sender_user_id = ? AND receiver_user_id = ?)
                OR (sender_user_id = ? AND receiver_user_id = ?)
             LIMIT 1'
        );
        $conversation->execute([(int) $user['id'], $receiverId, $receiverId, (int) $user['id']]);

        return (bool) $conversation->fetchColumn();
    }

    private function latestRideIdForUser(array $user): ?int
    {
        if ($user['role_code'] === 'driver') {
            $join = 'JOIN drivers ON drivers.id = rides.driver_id AND drivers.user_id = ?';
        } elseif ($user['role_code'] === 'student') {
            $join = 'JOIN bookings ON bookings.id = rides.booking_id JOIN students ON students.id = bookings.student_id AND students.user_id = ?';
        } else {
            $join = 'JOIN bookings ON bookings.id = rides.booking_id JOIN parents ON parents.id = bookings.parent_id AND parents.user_id = ?';
        }

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
