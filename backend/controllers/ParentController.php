<?php

namespace Controllers;

use Core\ApiController;
use Core\Response;

class ParentController extends ApiController
{
    private const ROUTE_BASE_DAILY_RATE = 40.0;
    private const ROUTE_PER_KM_RATE = 3.0;
    private const MONTHLY_ROUTE_DISCOUNT_RATE = 0.40;
    private const MONTHLY_NO_CLASS_REFUND_RATE = 0.50;
    private const DEFAULT_MONTHLY_ROUTE_TIME = '07:00:00';

    public function dashboard(): void
    {
        $user = $this->requireUser();
        $data = $this->dashboardData($user);
        Response::json(['success' => true, 'data' => $data]);
    }

    public function createStudent(): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $stmt = $this->pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $parentId = (int) $stmt->fetchColumn();

        if (!$parentId) {
            Response::json(['success' => false, 'message' => 'Parent profile not found.'], 404);
        }

        try {
            $name = trim((string) ($input['student_name'] ?? $input['studentName'] ?? ''));
            $lrn = trim((string) ($input['lrn'] ?? ''));
            $email = trim((string) ($input['email'] ?? ''));
            $mobileNumber = trim((string) ($input['mobile_number'] ?? $input['mobileNumber'] ?? ''));
            $password = trim((string) ($input['password'] ?? ''));

            if ($name === '' || $lrn === '') {
                Response::json(['success' => false, 'message' => 'Student name and LRN are required.'], 422);
            }

            if ($email === '') {
                $email = 'student-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($lrn)) . '-' . uniqid() . '@trace.local';
            }

            if ($mobileNumber === '') {
                $mobileNumber = 'student-' . uniqid();
            }

            [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');
            $coordinate = static fn ($value) => trim((string) $value) === '' ? null : (float) $value;
            $this->pdo->beginTransaction();
            $userId = $this->createUser('student', [
                'first_name' => $firstName ?: 'Student',
                'last_name' => $lastName ?: $user['last_name'],
                'email' => $email,
                'mobile_number' => $mobileNumber,
                'password' => $password !== '' ? $password : 'password',
            ], 'active');

            $stmt = $this->pdo->prepare(
                'INSERT INTO students (user_id, parent_id, lrn, school_name, grade_level, pickup_address, pickup_latitude, pickup_longitude, dropoff_address, dropoff_latitude, dropoff_longitude, medical_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $parentId,
                $lrn,
                $input['school_name'] ?? $input['schoolName'] ?? '',
                $input['grade_level'] ?? $input['gradeLevel'] ?? '',
                $input['pickup_address'] ?? $input['pickupAddress'] ?? '',
                $coordinate($input['pickup_latitude'] ?? $input['pickupLatitude'] ?? null),
                $coordinate($input['pickup_longitude'] ?? $input['pickupLongitude'] ?? null),
                $input['dropoff_address'] ?? $input['dropoffAddress'] ?? '',
                $coordinate($input['dropoff_latitude'] ?? $input['dropoffLatitude'] ?? null),
                $coordinate($input['dropoff_longitude'] ?? $input['dropoffLongitude'] ?? null),
                $input['medical_notes'] ?? $input['notes'] ?? null,
            ]);
            $this->notifyUser((int) $user['id'], 'Student added', 'A student account was linked to your profile.', 'student', $userId);
            $this->pdo->commit();

            Response::json(['success' => true, 'message' => 'Student registered.', 'data' => $this->dashboardData($user)], 201);
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $message = str_contains($exception->getMessage(), 'Duplicate entry')
                ? 'Student LRN, email, or mobile number is already registered.'
                : $exception->getMessage();
            Response::json(['success' => false, 'message' => $message], 422);
        }
    }

    public function updateStudent(array $params = []): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $studentId = (int) ($params['id'] ?? 0);
        $stmt = $this->pdo->prepare(
            'SELECT students.*, users.id AS student_user_id, users.first_name AS student_first_name,
                users.last_name AS student_last_name, users.email AS student_email, users.mobile_number AS student_mobile_number
             FROM students
             JOIN parents ON parents.id = students.parent_id
             JOIN users parent_user ON parent_user.id = parents.user_id
             JOIN users ON users.id = students.user_id
             WHERE students.id = ? AND parent_user.id = ?
             LIMIT 1'
        );
        $stmt->execute([$studentId, (int) $user['id']]);
        $student = $stmt->fetch();

        if (!$student) {
            Response::json(['success' => false, 'message' => 'Student not found.'], 404);
        }

        $name = trim((string) ($input['student_name'] ?? $input['studentName'] ?? ''));
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');
        $coordinate = static fn ($value) => trim((string) $value) === '' ? null : (float) $value;
        $lrn = trim((string) ($input['lrn'] ?? $student['lrn'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $mobileNumber = trim((string) ($input['mobile_number'] ?? $input['mobileNumber'] ?? ''));

        if ($lrn === '') {
            Response::json(['success' => false, 'message' => 'LRN is required.'], 422);
        }

        try {
            $this->pdo->beginTransaction();
            $userFields = ['first_name = ?', 'last_name = ?', 'email = ?', 'mobile_number = ?'];
            $userParams = [
                $firstName ?: $student['student_first_name'] ?: 'Student',
                $lastName ?: $student['student_last_name'] ?: $user['last_name'],
                $email !== '' ? $email : $student['student_email'],
                $mobileNumber !== '' ? $mobileNumber : $student['student_mobile_number'],
            ];

            if (!empty($input['password'])) {
                $userFields[] = 'password_hash = ?';
                $userParams[] = \Core\Auth::hashPassword((string) $input['password']);
            }

            $userParams[] = (int) $student['student_user_id'];
            $this->pdo->prepare('UPDATE users SET ' . implode(', ', $userFields) . ' WHERE id = ?')->execute($userParams);
            $this->pdo->prepare(
                'UPDATE students SET lrn = ?, school_name = ?, grade_level = ?, pickup_address = ?, pickup_latitude = ?, pickup_longitude = ?, dropoff_address = ?, dropoff_latitude = ?, dropoff_longitude = ?, medical_notes = ? WHERE id = ?'
            )->execute([
                $lrn,
                $input['school_name'] ?? $input['schoolName'] ?? '',
                $input['grade_level'] ?? $input['gradeLevel'] ?? '',
                $input['pickup_address'] ?? $input['pickupAddress'] ?? '',
                $coordinate($input['pickup_latitude'] ?? $input['pickupLatitude'] ?? null),
                $coordinate($input['pickup_longitude'] ?? $input['pickupLongitude'] ?? null),
                $input['dropoff_address'] ?? $input['dropoffAddress'] ?? '',
                $coordinate($input['dropoff_latitude'] ?? $input['dropoffLatitude'] ?? null),
                $coordinate($input['dropoff_longitude'] ?? $input['dropoffLongitude'] ?? null),
                $input['medical_notes'] ?? $input['notes'] ?? null,
                $studentId,
            ]);
            $this->notifyUser((int) $user['id'], 'Student updated', 'Student profile details were updated.', 'student', $studentId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $message = str_contains($exception->getMessage(), 'Duplicate entry')
                ? 'Student LRN, email, or mobile number is already registered.'
                : $exception->getMessage();
            Response::json(['success' => false, 'message' => $message], 422);
        }

        Response::json(['success' => true, 'message' => 'Student updated.', 'data' => $this->dashboardData($user)]);
    }

    public function drivers(): void
    {
        $this->requireUser();
        $drivers = $this->pdo->query(
            'SELECT drivers.id, users.first_name, users.last_name, users.profile_photo,
                drivers.license_number, drivers.license_expiry, drivers.license_photo_path, drivers.vehicle_plate_number,
                drivers.vehicle_model, drivers.vehicle_color, drivers.vehicle_photo_path,
                drivers.approval_status, drivers.is_online, drivers.current_latitude, drivers.current_longitude,
                vehicles.capacity, vehicles.registration_path
             FROM drivers
             JOIN users ON users.id = drivers.user_id
             LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
             WHERE drivers.approval_status = "approved" AND users.status = "active"
             ORDER BY drivers.is_online DESC, users.first_name'
        )->fetchAll();

        Response::json(['success' => true, 'data' => ['drivers' => array_map(fn ($driver) => [
            'id' => (int) $driver['id'],
            'name' => trim($driver['first_name'] . ' ' . $driver['last_name']),
            'vehicle' => trim($driver['vehicle_model'] . ' - ' . $driver['vehicle_plate_number']),
            'vehicleModel' => $driver['vehicle_model'],
            'vehiclePlateNumber' => $driver['vehicle_plate_number'],
            'vehicleColor' => $driver['vehicle_color'],
            'profilePhotoUrl' => $this->fileUrl($driver['profile_photo'] ?? null),
            'licenseNumber' => $driver['license_number'],
            'licenseExpiry' => $driver['license_expiry'],
            'licensePhotoUrl' => $this->fileUrl($driver['license_photo_path'] ?? null),
            'vehiclePhotoUrl' => $this->fileUrl($driver['vehicle_photo_path'] ?? null),
            'vehicleOrcrUrl' => $this->fileUrl($driver['registration_path'] ?? null),
            'vehicleCapacity' => (int) ($driver['capacity'] ?? 1),
            'approvalStatus' => $driver['approval_status'],
            'isOnline' => (bool) $driver['is_online'],
            'latitude' => $driver['current_latitude'] !== null && $driver['current_latitude'] !== '' ? (float) $driver['current_latitude'] : null,
            'longitude' => $driver['current_longitude'] !== null && $driver['current_longitude'] !== '' ? (float) $driver['current_longitude'] : null,
        ], $drivers)]]);
    }

    public function serviceEstimate(): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $parentId = $this->parentIdForUser((int) $user['id']);
        $estimate = $this->monthlyPlanEstimate(
            $parentId,
            $input['billing_month'] ?? $input['billingMonth'] ?? null,
            $this->selectedStudentIds($input),
            $this->normalizeMonthlyTripType($input['monthly_trip_type'] ?? $input['monthlyTripType'] ?? $input['trip_type'] ?? $input['tripType'] ?? null)
        );

        Response::json(['success' => true, 'data' => ['billing' => $estimate, 'monthlyPlan' => $estimate, 'monthlyPlans' => $this->activeMonthlyPlansForParent($parentId)]]);
    }

    public function payServiceAdvance(): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $parentId = $this->parentIdForUser((int) $user['id']);
        $estimate = $this->monthlyPlanEstimate(
            $parentId,
            $input['billing_month'] ?? $input['billingMonth'] ?? null,
            $this->selectedStudentIds($input),
            $this->normalizeMonthlyTripType($input['monthly_trip_type'] ?? $input['monthlyTripType'] ?? $input['trip_type'] ?? $input['tripType'] ?? null)
        );

        if (!$estimate['isPayable']) {
            Response::json(['success' => false, 'message' => $estimate['message']], 422);
        }

        $this->ensureMonthlyPlanTables();
        $this->ensureServicePaymentsTable();
        $this->ensureBookingMonthlyPlanColumns();
        $billingMonth = $estimate['serviceStartDate'] ?? ($estimate['billingMonth'] . '-01');
        $reference = trim((string) ($input['reference_number'] ?? $input['referenceNumber'] ?? 'ADV-' . strtoupper(bin2hex(random_bytes(4)))));
        $method = trim((string) ($input['payment_method'] ?? $input['paymentMethod'] ?? 'cash'));
        $scheduledTime = $this->normalizeScheduledTime($input['pickup_time'] ?? $input['pickupTime'] ?? $input['scheduled_time'] ?? $input['scheduledTime'] ?? self::DEFAULT_MONTHLY_ROUTE_TIME);
        $returnTime = $this->normalizeScheduledTime($input['dropoff_time'] ?? $input['dropoffTime'] ?? $input['return_time'] ?? $input['returnTime'] ?? '07:45');

        if ($this->minutesFromTime($returnTime) <= $this->minutesFromTime($scheduledTime)) {
            Response::json(['success' => false, 'message' => 'Drop-off time must be later than pickup time.'], 422);
        }

        try {
            $this->pdo->beginTransaction();
            $planId = $this->saveMonthlyPlan($parentId, $estimate, $scheduledTime, $returnTime);
            $stmt = $this->pdo->prepare(
                'INSERT INTO service_payments
                    (parent_id, monthly_plan_id, billing_month, route_count, service_days, daily_total, amount_due, amount_paid, payment_method, reference_number, status, route_snapshot, paid_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "paid", ?, NOW())'
            );
            $stmt->execute([
                $parentId,
                $planId,
                $billingMonth,
                $estimate['routeCount'],
                $estimate['serviceDays'],
                $estimate['dailyTotal'],
                $estimate['advanceAmount'],
                $estimate['advanceAmount'],
                $method,
                $reference,
                json_encode($estimate['routes']),
            ]);
            $this->generateMonthlyPlanBookings($parentId, $planId, $estimate, $scheduledTime, $returnTime);

            $this->notifyUser((int) $user['id'], 'Monthly plan paid', 'Your monthly payment plan is active and daily transport routes were opened for drivers.', 'payment', $planId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $message = str_contains($exception->getMessage(), 'Duplicate entry')
                ? 'Student LRN, email, or mobile number is already registered.'
                : $exception->getMessage();
            Response::json(['success' => false, 'message' => $message], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'Monthly plan paid and daily routes generated.',
            'data' => $this->dashboardData($user),
        ]);
    }

    public function updateMonthlyPlan(array $params = []): void
    {
        $user = $this->requireUser('parent');
        $input = $this->input();
        $parentId = $this->parentIdForUser((int) $user['id']);
        $planId = (int) ($params['id'] ?? 0);
        $this->ensureMonthlyPlanTables();
        $this->ensureServicePaymentsTable();
        $this->ensureBookingMonthlyPlanColumns();
        $plan = $this->monthlyPlanById($parentId, $planId);

        if (!$plan) {
            Response::json(['success' => false, 'message' => 'Monthly plan not found.'], 404);
        }

        $scheduledTime = $this->normalizeScheduledTime($input['pickup_time'] ?? $input['pickupTime'] ?? $plan['scheduled_time']);
        $returnTime = $this->normalizeScheduledTime($input['dropoff_time'] ?? $input['dropoffTime'] ?? $input['return_time'] ?? $input['returnTime'] ?? $plan['return_time']);

        if ($this->minutesFromTime($returnTime) <= $this->minutesFromTime($scheduledTime)) {
            Response::json(['success' => false, 'message' => 'Drop-off time must be later than pickup time.'], 422);
        }

        try {
            $this->pdo->beginTransaction();
            $selectedStudentIds = $this->applyMonthlyPlanRouteInput($parentId, $planId, $input['routes'] ?? []);
            $tripType = $this->normalizeMonthlyTripType($input['monthly_trip_type'] ?? $input['monthlyTripType'] ?? $input['trip_type'] ?? $input['tripType'] ?? $plan['trip_type'] ?? null);
            $estimate = $this->monthlyPlanEstimate($parentId, (string) $plan['billing_month'], $selectedStudentIds, $tripType);

            if (!$estimate['routes']) {
                throw new \RuntimeException('A monthly plan needs at least one completed route.');
            }

            $updatedPlanId = $this->saveMonthlyPlan($parentId, $estimate, $scheduledTime, $returnTime, $planId);
            $this->generateMonthlyPlanBookings($parentId, $updatedPlanId, $estimate, $scheduledTime, $returnTime);
            $this->updateServicePaymentSnapshot($planId, $estimate);
            $this->notifyUser((int) $user['id'], 'Monthly plan updated', 'Plan times and route locations were updated for the remaining daily transports.', 'payment', $planId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json(['success' => true, 'message' => 'Monthly plan updated.', 'data' => $this->dashboardData($user)]);
    }

    public function cancelMonthlyPlan(array $params = []): void
    {
        $user = $this->requireUser('parent');
        $parentId = $this->parentIdForUser((int) $user['id']);
        $planId = (int) ($params['id'] ?? 0);
        $this->ensureMonthlyPlanTables();
        $this->ensureServicePaymentsTable();
        $this->ensureBookingMonthlyPlanColumns();
        $plan = $this->monthlyPlanById($parentId, $planId);

        if (!$plan) {
            Response::json(['success' => false, 'message' => 'Monthly plan not found.'], 404);
        }

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('UPDATE monthly_plans SET status = "cancelled" WHERE id = ? AND parent_id = ?')->execute([$planId, $parentId]);
            $this->pdo->prepare('UPDATE service_payments SET status = "cancelled" WHERE monthly_plan_id = ?')->execute([$planId]);
            $this->pdo->prepare(
                'UPDATE rides
                 JOIN bookings ON bookings.id = rides.booking_id
                 SET rides.ride_status = "cancelled", rides.cancelled_at = COALESCE(rides.cancelled_at, NOW())
                 WHERE bookings.monthly_plan_id = ?
                   AND bookings.scheduled_date >= CURDATE()
                   AND rides.ride_status = "assigned"'
            )->execute([$planId]);
            $this->pdo->prepare(
                'UPDATE bookings
                 LEFT JOIN rides ON rides.booking_id = bookings.id
                 SET bookings.booking_status = "cancelled", bookings.assigned_driver_id = NULL
                 WHERE bookings.monthly_plan_id = ?
                   AND bookings.scheduled_date >= CURDATE()
                   AND bookings.booking_status NOT IN ("completed", "cancelled")
                   AND (rides.id IS NULL OR rides.ride_status = "cancelled")'
            )->execute([$planId]);
            $this->notifyUser((int) $user['id'], 'Monthly plan cancelled', 'Remaining unstarted daily transports were removed from the active schedule.', 'payment', $planId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json(['success' => true, 'message' => 'Monthly plan cancelled.', 'data' => $this->dashboardData($user)]);
    }

    public function cancelMonthlyPlanDay(array $params = []): void
    {
        $user = $this->requireUser('parent');
        $parentId = $this->parentIdForUser((int) $user['id']);
        $bookingId = (int) ($params['id'] ?? 0);
        $input = $this->input();
        $reason = trim((string) ($input['reason'] ?? 'No class, holiday, or student absence.'));
        $this->ensureBookingMonthlyPlanColumns();
        $this->ensureMonthlyPlanRefundsTable();

        $bookingStmt = $this->pdo->prepare(
            'SELECT *
             FROM bookings
             WHERE id = ?
               AND parent_id = ?
               AND monthly_plan_id IS NOT NULL
               AND monthly_plan_route_id IS NOT NULL
             LIMIT 1'
        );
        $bookingStmt->execute([$bookingId, $parentId]);
        $booking = $bookingStmt->fetch();

        if (!$booking) {
            Response::json(['success' => false, 'message' => 'Monthly plan daily trip not found.'], 404);
        }

        if ((string) $booking['scheduled_date'] < date('Y-m-d')) {
            Response::json(['success' => false, 'message' => 'Past monthly trips cannot be cancelled for refund.'], 422);
        }

        try {
            $this->pdo->beginTransaction();
            $rowsStmt = $this->pdo->prepare(
                'SELECT bookings.id, bookings.driver_payout_amount, bookings.assigned_driver_id, bookings.scheduled_date,
                    bookings.booking_status, rides.id AS ride_id, rides.ride_status, drivers.user_id AS driver_user_id
                 FROM bookings
                 LEFT JOIN rides ON rides.booking_id = bookings.id
                 LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
                 WHERE bookings.parent_id = ?
                   AND bookings.monthly_plan_id = ?
                   AND bookings.monthly_plan_route_id = ?
                   AND bookings.scheduled_date = ?
                   AND bookings.booking_status IN ("pending", "assigned")
                   AND (rides.id IS NULL OR rides.ride_status = "assigned")'
            );
            $rowsStmt->execute([
                $parentId,
                (int) $booking['monthly_plan_id'],
                (int) $booking['monthly_plan_route_id'],
                $booking['scheduled_date'],
            ]);
            $rows = $rowsStmt->fetchAll();

            if (!$rows) {
                throw new \RuntimeException('Only pending or assigned monthly trips can be cancelled for no-class refund.');
            }

            $ids = array_map(fn ($row) => (int) $row['id'], $rows);
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));
            $refundAmount = round(array_reduce($rows, fn ($sum, $row) => $sum + (float) $row['driver_payout_amount'], 0.0) * self::MONTHLY_NO_CLASS_REFUND_RATE, 2);
            $reference = 'REFUND-' . strtoupper(bin2hex(random_bytes(3)));

            $this->pdo->prepare(
                'UPDATE rides
                 SET ride_status = "cancelled", cancelled_at = COALESCE(cancelled_at, NOW())
                 WHERE booking_id IN (' . $placeholders . ')
                   AND ride_status = "assigned"'
            )->execute($ids);
            $this->pdo->prepare(
                'UPDATE bookings
                 SET booking_status = "cancelled", assigned_driver_id = NULL
                 WHERE id IN (' . $placeholders . ')'
            )->execute($ids);
            $this->pdo->prepare(
                'INSERT INTO monthly_plan_refunds
                    (parent_id, monthly_plan_id, monthly_plan_route_id, booking_id, scheduled_date, refund_amount, reason, reference_number, status, refunded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "approved", NOW())'
            )->execute([
                $parentId,
                (int) $booking['monthly_plan_id'],
                (int) $booking['monthly_plan_route_id'],
                $bookingId,
                $booking['scheduled_date'],
                $refundAmount,
                $reason,
                $reference,
            ]);

            $driverUserIds = array_values(array_unique(array_filter(array_map(fn ($row) => (int) ($row['driver_user_id'] ?? 0), $rows))));

            foreach ($driverUserIds as $driverUserId) {
                $this->notifyUser($driverUserId, 'Daily trip cancelled', 'Parent cancelled the monthly plan trip for ' . $booking['scheduled_date'] . '. Reason: ' . $reason, 'monthly_plan_day_cancelled', $bookingId);
            }

            $this->notifyUser((int) $user['id'], 'Daily trip cancelled', 'Refund recorded: PHP ' . number_format($refundAmount, 2) . ' for ' . $booking['scheduled_date'] . '.', 'monthly_plan_refund', $bookingId);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Response::json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        Response::json([
            'success' => true,
            'message' => 'Daily trip cancelled. A 50% refund was recorded.',
            'data' => $this->dashboardData($user),
        ]);
    }

    private function ensureRecurringGroupColumn(): void
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM bookings LIKE 'recurring_group_id'");

        if (!$stmt->fetch()) {
            $this->pdo->exec('ALTER TABLE bookings ADD COLUMN recurring_group_id VARCHAR(64) NULL AFTER assigned_driver_id');
        }
    }

    private function ensureServicePaymentsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS service_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id BIGINT UNSIGNED NOT NULL,
                monthly_plan_id BIGINT UNSIGNED NULL,
                billing_month DATE NOT NULL,
                route_count INT UNSIGNED NOT NULL DEFAULT 0,
                service_days INT UNSIGNED NOT NULL DEFAULT 0,
                daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                amount_due DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                payment_method VARCHAR(50) NOT NULL DEFAULT "cash",
                reference_number VARCHAR(100) NULL,
                status ENUM("pending", "paid", "cancelled") NOT NULL DEFAULT "pending",
                route_snapshot TEXT NULL,
                paid_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_service_payments_parent_month (parent_id, billing_month),
                INDEX idx_service_payments_plan (monthly_plan_id),
                CONSTRAINT fk_service_payments_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
            )'
        );

        if (!$this->columnExists('service_payments', 'monthly_plan_id')) {
            $this->pdo->exec('ALTER TABLE service_payments ADD COLUMN monthly_plan_id BIGINT UNSIGNED NULL AFTER parent_id');
        }

        $this->ensureIndex('service_payments', 'idx_service_payments_parent_month', 'parent_id, billing_month');
        $this->ensureIndex('service_payments', 'idx_service_payments_plan', 'monthly_plan_id');
        $this->dropIndexIfExists('service_payments', 'uniq_service_payments_parent_month');
    }

    private function ensureMonthlyPlanRefundsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS monthly_plan_refunds (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id BIGINT UNSIGNED NOT NULL,
                monthly_plan_id BIGINT UNSIGNED NOT NULL,
                monthly_plan_route_id BIGINT UNSIGNED NOT NULL,
                booking_id BIGINT UNSIGNED NULL,
                scheduled_date DATE NOT NULL,
                refund_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                reason TEXT NULL,
                reference_number VARCHAR(80) NULL,
                status ENUM("pending", "approved", "paid", "cancelled") NOT NULL DEFAULT "approved",
                refunded_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_monthly_plan_refunds_parent (parent_id, scheduled_date),
                INDEX idx_monthly_plan_refunds_plan_day (monthly_plan_id, monthly_plan_route_id, scheduled_date),
                CONSTRAINT fk_monthly_plan_refunds_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
            )'
        );
    }

    private function ensureMonthlyPlanTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS monthly_plans (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                parent_id BIGINT UNSIGNED NOT NULL,
                billing_month DATE NOT NULL,
                destination_key VARCHAR(80) NULL,
                destination_address TEXT NULL,
                destination_latitude DECIMAL(10, 7) NULL,
                destination_longitude DECIMAL(10, 7) NULL,
                trip_type VARCHAR(20) NOT NULL DEFAULT "one_way",
                route_count INT UNSIGNED NOT NULL DEFAULT 0,
                child_count INT UNSIGNED NOT NULL DEFAULT 0,
                service_days INT UNSIGNED NOT NULL DEFAULT 0,
                gross_daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                net_daily_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                monthly_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                scheduled_time TIME NOT NULL DEFAULT "07:00:00",
                return_time TIME NULL,
                status ENUM("draft", "active", "cancelled") NOT NULL DEFAULT "draft",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_monthly_plans_parent_month (parent_id, billing_month),
                INDEX idx_monthly_plans_destination (parent_id, billing_month, destination_key),
                CONSTRAINT fk_monthly_plans_parent FOREIGN KEY (parent_id) REFERENCES parents(id)
            )'
        );
        foreach ([
            'destination_key' => 'ALTER TABLE monthly_plans ADD COLUMN destination_key VARCHAR(80) NULL AFTER billing_month',
            'destination_address' => 'ALTER TABLE monthly_plans ADD COLUMN destination_address TEXT NULL AFTER destination_key',
            'destination_latitude' => 'ALTER TABLE monthly_plans ADD COLUMN destination_latitude DECIMAL(10, 7) NULL AFTER destination_address',
            'destination_longitude' => 'ALTER TABLE monthly_plans ADD COLUMN destination_longitude DECIMAL(10, 7) NULL AFTER destination_latitude',
            'trip_type' => 'ALTER TABLE monthly_plans ADD COLUMN trip_type VARCHAR(20) NOT NULL DEFAULT "one_way" AFTER destination_longitude',
            'return_time' => 'ALTER TABLE monthly_plans ADD COLUMN return_time TIME NULL AFTER scheduled_time',
        ] as $column => $sql) {
            if (!$this->columnExists('monthly_plans', $column)) {
                $this->pdo->exec($sql);
            }
        }

        $this->ensureIndex('monthly_plans', 'idx_monthly_plans_parent_month', 'parent_id, billing_month');
        $this->ensureIndex('monthly_plans', 'idx_monthly_plans_destination', 'parent_id, billing_month, destination_key');
        $this->dropIndexIfExists('monthly_plans', 'uniq_monthly_plans_parent_month');
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS monthly_plan_routes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                monthly_plan_id BIGINT UNSIGNED NOT NULL,
                destination_key VARCHAR(80) NOT NULL,
                destination_address TEXT NOT NULL,
                destination_latitude DECIMAL(10, 7) NULL,
                destination_longitude DECIMAL(10, 7) NULL,
                child_count INT UNSIGNED NOT NULL DEFAULT 0,
                gross_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                net_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_monthly_plan_routes_destination (monthly_plan_id, destination_key),
                CONSTRAINT fk_monthly_plan_routes_plan FOREIGN KEY (monthly_plan_id) REFERENCES monthly_plans(id) ON DELETE CASCADE
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS monthly_plan_route_students (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                monthly_plan_route_id BIGINT UNSIGNED NOT NULL,
                student_id BIGINT UNSIGNED NOT NULL,
                pickup_address TEXT NOT NULL,
                pickup_latitude DECIMAL(10, 7) NULL,
                pickup_longitude DECIMAL(10, 7) NULL,
                distance_km DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
                gross_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                net_daily_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_monthly_plan_route_students (monthly_plan_route_id, student_id),
                CONSTRAINT fk_monthly_plan_route_students_route FOREIGN KEY (monthly_plan_route_id) REFERENCES monthly_plan_routes(id) ON DELETE CASCADE,
                CONSTRAINT fk_monthly_plan_route_students_student FOREIGN KEY (student_id) REFERENCES students(id)
            )'
        );
    }

    private function ensureBookingMonthlyPlanColumns(): void
    {
        $this->ensureRecurringGroupColumn();
        $columns = [
            'monthly_plan_id' => 'ALTER TABLE bookings ADD COLUMN monthly_plan_id BIGINT UNSIGNED NULL AFTER recurring_group_id',
            'monthly_plan_route_id' => 'ALTER TABLE bookings ADD COLUMN monthly_plan_route_id BIGINT UNSIGNED NULL AFTER monthly_plan_id',
            'driver_payout_amount' => 'ALTER TABLE bookings ADD COLUMN driver_payout_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER monthly_plan_route_id',
            'pickup_time' => 'ALTER TABLE bookings ADD COLUMN pickup_time TIME NULL AFTER scheduled_time',
            'dropoff_time' => 'ALTER TABLE bookings ADD COLUMN dropoff_time TIME NULL AFTER pickup_time',
            'adjusted_pickup_time' => 'ALTER TABLE bookings ADD COLUMN adjusted_pickup_time TIME NULL AFTER dropoff_time',
        ];

        foreach ($columns as $column => $sql) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM bookings LIKE '" . $column . "'");

            if (!$stmt->fetch()) {
                $this->pdo->exec($sql);
            }
        }
    }

    private function parentIdForUser(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $parentId = (int) $stmt->fetchColumn();

        if ($parentId <= 0) {
            Response::json(['success' => false, 'message' => 'Parent profile not found.'], 404);
        }

        return $parentId;
    }

    private function selectedStudentIds(array $input): array
    {
        $raw = $input['student_ids'] ?? $input['studentIds'] ?? $input['children'] ?? [];

        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }

        if (!is_array($raw)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), fn ($id) => $id > 0)));

        return $ids;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
        $stmt->execute([$column]);

        return (bool) $stmt->fetch();
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->pdo->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?');
        $stmt->execute([$index]);

        return (bool) $stmt->fetch();
    }

    private function ensureIndex(string $table, string $index, string $columns): void
    {
        if (!$this->indexExists($table, $index)) {
            $this->pdo->exec('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' (' . $columns . ')');
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            $this->pdo->exec('ALTER TABLE ' . $table . ' DROP INDEX ' . $index);
        }
    }

    private function monthlyPlanEstimate(int $parentId, ?string $billingMonth = null, array $selectedStudentIds = [], string $tripType = 'one_way'): array
    {
        $this->ensureMonthlyPlanTables();
        $this->ensureServicePaymentsTable();
        $serviceStartDate = $this->normalizeBillingDate($billingMonth);
        $month = substr($serviceStartDate, 0, 7);
        $monthStart = $serviceStartDate;
        $serviceDays = $this->monthlyServiceDays($monthStart);
        $tripType = $this->normalizeMonthlyTripType($tripType);
        $tripMultiplier = $tripType === 'round_trip' ? 2 : 1;
        $allStudents = $this->studentsForParent($parentId);
        $students = $selectedStudentIds
            ? array_values(array_filter($allStudents, fn ($student) => in_array((int) $student['id'], $selectedStudentIds, true)))
            : $allStudents;
        $routeGroups = [];
        $completeChildren = 0;

        foreach ($students as $student) {
            $pickupLat = $this->nullableFloat($student['pickupLatitude'] ?? null);
            $pickupLng = $this->nullableFloat($student['pickupLongitude'] ?? null);
            $dropoffLat = $this->nullableFloat($student['dropoffLatitude'] ?? null);
            $dropoffLng = $this->nullableFloat($student['dropoffLongitude'] ?? null);
            $hasAddresses = trim((string) ($student['pickupAddress'] ?? '')) !== '' && trim((string) ($student['dropoffAddress'] ?? '')) !== '';
            $hasCoordinates = $pickupLat !== null && $pickupLng !== null && $dropoffLat !== null && $dropoffLng !== null;
            $routeComplete = $hasAddresses && $hasCoordinates && !$this->sameLocation(
                (string) $student['pickupAddress'],
                (string) $student['dropoffAddress'],
                $pickupLat,
                $pickupLng,
                $dropoffLat,
                $dropoffLng
            );
            if (!$routeComplete) {
                $routeGroups['incomplete'][] = [
                    'studentId' => (int) $student['id'],
                    'studentName' => $student['name'],
                    'pickupAddress' => $student['pickupAddress'],
                    'dropoffAddress' => $student['dropoffAddress'],
                    'distanceKm' => 0,
                    'grossDailyAmount' => 0,
                    'netDailyAmount' => 0,
                    'routeComplete' => false,
                ];
                continue;
            }

            $completeChildren++;
            $destinationKey = $this->destinationKey((string) $student['dropoffAddress'], $dropoffLat, $dropoffLng);
            $distanceKm = $this->distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
            $grossDailyAmount = self::ROUTE_BASE_DAILY_RATE + ($distanceKm * self::ROUTE_PER_KM_RATE);

            if (!isset($routeGroups[$destinationKey])) {
                $routeGroups[$destinationKey] = [
                    'destinationKey' => $destinationKey,
                    'destinationAddress' => $student['dropoffAddress'],
                    'destinationLatitude' => $dropoffLat,
                    'destinationLongitude' => $dropoffLng,
                    'children' => [],
                    'childCount' => 0,
                    'grossDailyAmount' => 0.0,
                    'discountAmount' => 0.0,
                    'netDailyAmount' => 0.0,
                    'monthlyAmount' => 0.0,
                    'routeComplete' => true,
                ];
            }

            $isExtraChild = count($students) > 1 && $completeChildren > 1;
            $childDiscountAmount = $isExtraChild ? round($grossDailyAmount * self::MONTHLY_ROUTE_DISCOUNT_RATE, 2) : 0.0;
            $childNetDailyAmount = round($grossDailyAmount - $childDiscountAmount, 2);

            $routeGroups[$destinationKey]['children'][] = [
                'studentId' => (int) $student['id'],
                'studentName' => $student['name'],
                'pickupAddress' => $student['pickupAddress'],
                'pickupLatitude' => $pickupLat,
                'pickupLongitude' => $pickupLng,
                'dropoffAddress' => $student['dropoffAddress'],
                'distanceKm' => round($distanceKm, 2),
                'grossDailyAmount' => round($grossDailyAmount, 2),
                'discountAmount' => $childDiscountAmount,
                'discountRate' => $isExtraChild ? self::MONTHLY_ROUTE_DISCOUNT_RATE : 0.0,
                'netDailyAmount' => $childNetDailyAmount,
                'routeComplete' => true,
            ];
            $routeGroups[$destinationKey]['childCount']++;
            $routeGroups[$destinationKey]['grossDailyAmount'] += $grossDailyAmount;
            $routeGroups[$destinationKey]['discountAmount'] += $childDiscountAmount;
            $routeGroups[$destinationKey]['netDailyAmount'] += $childNetDailyAmount;
        }

        unset($routeGroups['incomplete']);
        $routes = array_values($routeGroups);
        $grossDailyTotal = 0.0;
        $discountTotal = 0.0;
        $netDailyTotal = 0.0;

        foreach ($routes as &$route) {
            $route['grossDailyAmount'] = round($route['grossDailyAmount'], 2);
            $route['discountAmount'] = round($route['discountAmount'], 2);
            $route['netDailyAmount'] = round($route['netDailyAmount'], 2);
            $route['monthlyAmount'] = round($route['netDailyAmount'] * $tripMultiplier * $serviceDays, 2);
            $grossDailyTotal += $route['grossDailyAmount'];
            $discountTotal += $route['discountAmount'];
            $netDailyTotal += $route['netDailyAmount'];

            foreach ($route['children'] as &$child) {
                $child['monthlyAmount'] = round($child['netDailyAmount'] * $tripMultiplier * $serviceDays, 2);
            }
            unset($child);
        }
        unset($route);

        $destinationKey = $routes[0]['destinationKey'] ?? null;
        $activePlan = $destinationKey ? $this->monthlyPlanForRoute($parentId, $monthStart, $destinationKey) : null;
        $payment = $activePlan ? $this->servicePaymentForPlan((int) $activePlan['id']) : null;
        $grossDailyTotal *= $tripMultiplier;
        $discountTotal *= $tripMultiplier;
        $netDailyTotal *= $tripMultiplier;
        $advanceAmount = round($netDailyTotal * $serviceDays, 2);
        $paidAmount = $payment ? (float) $payment['amount_paid'] : 0.0;
        $paid = $payment && $activePlan && $payment['status'] === 'paid' && $paidAmount >= $advanceAmount;
        $isPayable = !$paid && $completeChildren > 0 && $completeChildren === count($students) && count($routes) >= 1;
        $message = 'Select at least one child with a complete pickup and drop-off route before paying the monthly plan.';

        if (!$selectedStudentIds) {
            $message = 'Select the child or children for this monthly route plan.';
        } elseif (count($students) !== count($selectedStudentIds)) {
            $message = 'One or more selected children do not belong to this parent account.';
        } elseif ($students && $completeChildren !== count($students)) {
            $message = 'Complete every selected child route with valid pickup and drop-off pins before paying.';
        } elseif ($paid) {
            $message = 'This billing month is already paid.';
        } elseif ($isPayable) {
            $message = 'Ready for one-month advance payment. Extra children receive the monthly discount.';
        }

        return [
            'billingMonth' => $month,
            'serviceStartDate' => $serviceStartDate,
            'monthLabel' => (new \DateTime($monthStart))->format('F Y'),
            'serviceDays' => $serviceDays,
            'tripType' => $tripType,
            'tripTypeLabel' => $tripType === 'round_trip' ? 'Round trip' : 'One way',
            'tripMultiplier' => $tripMultiplier,
            'routeCount' => count($routes),
            'childCount' => count($students),
            'selectedStudentIds' => array_map(fn ($student) => (int) $student['id'], $students),
            'selectedChildCount' => count($students),
            'baseDailyRate' => self::ROUTE_BASE_DAILY_RATE,
            'perKmRate' => self::ROUTE_PER_KM_RATE,
            'discountRate' => self::MONTHLY_ROUTE_DISCOUNT_RATE,
            'grossDailyTotal' => round($grossDailyTotal, 2),
            'discountAmount' => round($discountTotal, 2),
            'dailyTotal' => round($netDailyTotal, 2),
            'advanceAmount' => $advanceAmount,
            'refundPerNoClassDay' => round($netDailyTotal * self::MONTHLY_NO_CLASS_REFUND_RATE, 2),
            'refundRate' => self::MONTHLY_NO_CLASS_REFUND_RATE,
            'noClassRefundPolicy' => 'Refund 50% of the cancelled daily trip for no-class days, holidays, or student absences.',
            'paid' => $paid,
            'paymentStatus' => $payment['status'] ?? 'pending',
            'paymentId' => $payment ? (int) $payment['id'] : null,
            'amountPaid' => round($paidAmount, 2),
            'paidAt' => $payment['paid_at'] ?? null,
            'planId' => $activePlan ? (int) $activePlan['id'] : null,
            'isPayable' => $isPayable,
            'message' => $message,
            'routes' => $routes,
        ];
    }

    private function destinationKey(string $address, ?float $latitude, ?float $longitude): string
    {
        if ($latitude !== null && $longitude !== null) {
            return number_format($latitude, 5, '.', '') . ',' . number_format($longitude, 5, '.', '');
        }

        return substr(sha1(strtolower(trim($address))), 0, 32);
    }

    private function saveMonthlyPlan(int $parentId, array $estimate, string $scheduledTime, string $returnTime, ?int $targetPlanId = null): int
    {
        $billingMonth = $estimate['serviceStartDate'] ?? ($estimate['billingMonth'] . '-01');
        $route = $estimate['routes'][0] ?? null;

        if (!$route) {
            Response::json(['success' => false, 'message' => 'A monthly plan needs at least one completed route.'], 422);
        }

        $existingPlan = $targetPlanId ? $this->monthlyPlanById($parentId, $targetPlanId, true) : $this->monthlyPlanForRoute($parentId, $billingMonth, $route['destinationKey']);
        $payload = [
            $parentId,
            $billingMonth,
            $route['destinationKey'],
            $route['destinationAddress'],
            $route['destinationLatitude'],
            $route['destinationLongitude'],
            $estimate['tripType'] ?? 'one_way',
            $estimate['routeCount'],
            $estimate['childCount'],
            $estimate['serviceDays'],
            $estimate['grossDailyTotal'],
            $estimate['discountAmount'],
            $estimate['dailyTotal'],
            $estimate['advanceAmount'],
            $scheduledTime,
            $returnTime,
        ];

        if ($existingPlan) {
            $payload[] = (int) $existingPlan['id'];
            $this->pdo->prepare(
                'UPDATE monthly_plans
                 SET parent_id = ?, billing_month = ?, destination_key = ?, destination_address = ?, destination_latitude = ?, destination_longitude = ?,
                     trip_type = ?, route_count = ?, child_count = ?, service_days = ?, gross_daily_total = ?, discount_amount = ?, net_daily_total = ?,
                     monthly_amount = ?, scheduled_time = ?, return_time = ?, status = "active"
                  WHERE id = ?'
            )->execute($payload);
            $planId = (int) $existingPlan['id'];
        } else {
            $this->pdo->prepare(
                'INSERT INTO monthly_plans
                    (parent_id, billing_month, destination_key, destination_address, destination_latitude, destination_longitude,
                     trip_type, route_count, child_count, service_days, gross_daily_total, discount_amount, net_daily_total, monthly_amount, scheduled_time, return_time, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
            )->execute($payload);
            $planId = (int) $this->pdo->lastInsertId();
        }

        $this->pdo->prepare('DELETE FROM monthly_plan_routes WHERE monthly_plan_id = ?')->execute([$planId]);
        $routeStmt = $this->pdo->prepare(
            'INSERT INTO monthly_plan_routes
                (monthly_plan_id, destination_key, destination_address, destination_latitude, destination_longitude, child_count, gross_daily_amount, discount_amount, net_daily_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $childStmt = $this->pdo->prepare(
            'INSERT INTO monthly_plan_route_students
                (monthly_plan_route_id, student_id, pickup_address, pickup_latitude, pickup_longitude, distance_km, gross_daily_amount, net_daily_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($estimate['routes'] as $route) {
            $routeStmt->execute([
                $planId,
                $route['destinationKey'],
                $route['destinationAddress'],
                $route['destinationLatitude'],
                $route['destinationLongitude'],
                $route['childCount'],
                $route['grossDailyAmount'],
                $route['discountAmount'],
                $route['netDailyAmount'],
            ]);
            $routeId = (int) $this->pdo->lastInsertId();

            foreach ($route['children'] as $child) {
                $childStmt->execute([
                    $routeId,
                    $child['studentId'],
                    $child['pickupAddress'],
                    $child['pickupLatitude'],
                    $child['pickupLongitude'],
                    $child['distanceKm'],
                    $child['grossDailyAmount'],
                    $child['netDailyAmount'],
                ]);
            }
        }

        return $planId;
    }

    private function generateMonthlyPlanBookings(int $parentId, int $planId, array $estimate, string $scheduledTime, string $returnTime): void
    {
        $existing = $this->pdo->prepare(
            'SELECT bookings.id
             FROM bookings
             LEFT JOIN rides ON rides.booking_id = bookings.id
             WHERE bookings.monthly_plan_id = ? AND rides.id IS NULL'
        );
        $existing->execute([$planId]);
        $bookingIds = array_map('intval', $existing->fetchAll(\PDO::FETCH_COLUMN));

        if ($bookingIds) {
            $placeholders = implode(', ', array_fill(0, count($bookingIds), '?'));
            $this->pdo->prepare('DELETE FROM bookings WHERE id IN (' . $placeholders . ')')->execute($bookingIds);
        }

        $routeIds = $this->pdo->prepare('SELECT id, destination_key FROM monthly_plan_routes WHERE monthly_plan_id = ?');
        $routeIds->execute([$planId]);
        $routeIdByKey = [];

        foreach ($routeIds->fetchAll() as $route) {
            $routeIdByKey[$route['destination_key']] = (int) $route['id'];
        }

        $monthStart = new \DateTime($estimate['serviceStartDate'] ?? ($estimate['billingMonth'] . '-01'));
        $days = $this->monthlyServiceDays($monthStart->format('Y-m-d'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO bookings
                (parent_id, student_id, pickup_address, dropoff_address, pickup_latitude, pickup_longitude, dropoff_latitude, dropoff_longitude,
                 scheduled_date, scheduled_time, pickup_time, dropoff_time, adjusted_pickup_time, trip_type, notes, booking_status, assigned_driver_id, recurring_group_id, monthly_plan_id, monthly_plan_route_id, driver_payout_amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, "recurring", ?, "pending", NULL, ?, ?, ?, ?)'
        );
        $existingForDay = $this->pdo->prepare(
            'SELECT bookings.id
             FROM bookings
             WHERE bookings.monthly_plan_id = ?
               AND bookings.student_id = ?
               AND bookings.scheduled_date = ?
               AND LOWER(COALESCE(bookings.notes, "")) = ?
               AND bookings.booking_status <> "cancelled"
              LIMIT 1'
        );
        $directions = ($estimate['tripType'] ?? 'one_way') === 'round_trip'
            ? ['to_school', 'return_home']
            : ['to_school'];

        foreach ($estimate['routes'] as $route) {
            $routeId = $routeIdByKey[$route['destinationKey']] ?? null;

            if (!$routeId) {
                continue;
            }

            for ($day = 0, $serviceDays = 0; $serviceDays < $days; $day++) {
                $serviceDate = (clone $monthStart)->modify('+' . $day . ' days');

                if (!$this->isWeekday($serviceDate)) {
                    continue;
                }

                $serviceDays++;

                $date = $serviceDate->format('Y-m-d');
                $dailyRouteGroup = 'plan-' . $planId . '-route-' . $routeId . '-' . $date;

                foreach ($route['children'] as $child) {
                    foreach ($directions as $direction) {
                        $isReturn = $direction === 'return_home';
                        $note = $isReturn ? 'monthly payment plan return home' : 'monthly payment plan pickup to drop-off';
                        $existingForDay->execute([$planId, $child['studentId'], $date, $note]);

                        if ($existingForDay->fetchColumn()) {
                            continue;
                        }

                        $stmt->execute([
                            $parentId,
                            $child['studentId'],
                            $isReturn ? $route['destinationAddress'] : $child['pickupAddress'],
                            $isReturn ? $child['pickupAddress'] : $route['destinationAddress'],
                            $isReturn ? $route['destinationLatitude'] : $child['pickupLatitude'],
                            $isReturn ? $route['destinationLongitude'] : $child['pickupLongitude'],
                            $isReturn ? $child['pickupLatitude'] : $route['destinationLatitude'],
                            $isReturn ? $child['pickupLongitude'] : $route['destinationLongitude'],
                            $date,
                            $isReturn ? $returnTime : $scheduledTime,
                            $isReturn ? $returnTime : $scheduledTime,
                            $isReturn ? null : $returnTime,
                            $note,
                            $dailyRouteGroup . '-' . $direction,
                            $planId,
                            $routeId,
                            $child['netDailyAmount'],
                        ]);
                    }
                }
            }
        }
    }

    private function monthlyPlanById(int $parentId, int $planId, bool $includeCancelled = false): ?array
    {
        $statusSql = $includeCancelled ? '' : ' AND status = "active"';
        $stmt = $this->pdo->prepare('SELECT * FROM monthly_plans WHERE id = ? AND parent_id = ?' . $statusSql . ' LIMIT 1');
        $stmt->execute([$planId, $parentId]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    private function applyMonthlyPlanRouteInput(int $parentId, int $planId, array $routes): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT monthly_plan_routes.id AS route_id, monthly_plan_route_students.student_id
             FROM monthly_plan_routes
             JOIN monthly_plan_route_students ON monthly_plan_route_students.monthly_plan_route_id = monthly_plan_routes.id
             JOIN students ON students.id = monthly_plan_route_students.student_id
             WHERE monthly_plan_routes.monthly_plan_id = ?
               AND students.parent_id = ?'
        );
        $stmt->execute([$planId, $parentId]);
        $memberships = [];
        $selectedStudentIds = [];

        foreach ($stmt->fetchAll() as $row) {
            $routeId = (int) $row['route_id'];
            $studentId = (int) $row['student_id'];
            $memberships[$routeId][] = $studentId;
            $selectedStudentIds[$studentId] = true;
        }

        if (!$memberships) {
            throw new \RuntimeException('Monthly plan routes were not found.');
        }

        if (!$routes) {
            return array_keys($selectedStudentIds);
        }

        $updateStudent = $this->pdo->prepare(
            'UPDATE students
             SET pickup_address = ?, pickup_latitude = ?, pickup_longitude = ?,
                 dropoff_address = ?, dropoff_latitude = ?, dropoff_longitude = ?
             WHERE id = ? AND parent_id = ?'
        );

        foreach ($routes as $route) {
            $routeId = (int) ($route['routeId'] ?? $route['id'] ?? 0);

            if (!$routeId || !isset($memberships[$routeId])) {
                throw new \RuntimeException('A monthly plan route does not belong to this subscription.');
            }

            $destinationAddress = trim((string) ($route['destinationAddress'] ?? $route['dropoffAddress'] ?? ''));
            $destinationLatitude = $this->nullableFloat($route['destinationLatitude'] ?? $route['dropoffLatitude'] ?? null);
            $destinationLongitude = $this->nullableFloat($route['destinationLongitude'] ?? $route['dropoffLongitude'] ?? null);

            if ($destinationAddress === '' || $destinationLatitude === null || $destinationLongitude === null) {
                throw new \RuntimeException('Every monthly route needs a valid drop-off address and pin.');
            }

            $children = $route['children'] ?? [];

            if (!$children) {
                $children = array_map(fn ($studentId) => ['studentId' => $studentId], $memberships[$routeId]);
            }

            foreach ($children as $child) {
                $studentId = (int) ($child['studentId'] ?? $child['id'] ?? 0);

                if (!in_array($studentId, $memberships[$routeId], true)) {
                    throw new \RuntimeException('A selected child does not belong to this monthly route.');
                }

                $pickupAddress = trim((string) ($child['pickupAddress'] ?? ''));
                $pickupLatitude = $this->nullableFloat($child['pickupLatitude'] ?? null);
                $pickupLongitude = $this->nullableFloat($child['pickupLongitude'] ?? null);

                if ($pickupAddress === '' || $pickupLatitude === null || $pickupLongitude === null) {
                    throw new \RuntimeException('Every child needs a valid pickup address and pin.');
                }

                $updateStudent->execute([
                    $pickupAddress,
                    $pickupLatitude,
                    $pickupLongitude,
                    $destinationAddress,
                    $destinationLatitude,
                    $destinationLongitude,
                    $studentId,
                    $parentId,
                ]);
            }
        }

        return array_keys($selectedStudentIds);
    }

    private function updateServicePaymentSnapshot(int $planId, array $estimate): void
    {
        $this->pdo->prepare(
            'UPDATE service_payments
             SET route_count = ?, service_days = ?, daily_total = ?, amount_due = ?, route_snapshot = ?
             WHERE monthly_plan_id = ? AND status = "paid"'
        )->execute([
            $estimate['routeCount'],
            $estimate['serviceDays'],
            $estimate['dailyTotal'],
            $estimate['advanceAmount'],
            json_encode($estimate['routes']),
            $planId,
        ]);
    }

    private function activeMonthlyPlansForParent(int $parentId): array
    {
        $this->ensureMonthlyPlanTables();
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM monthly_plans
             WHERE parent_id = ? AND status = "active"
             ORDER BY billing_month DESC, id DESC'
        );
        $stmt->execute([$parentId]);

        return array_map(fn ($plan) => $this->monthlyPlanResource($plan), $stmt->fetchAll());
    }

    private function monthlyPlanResource(array $plan): array
    {
        $routeStmt = $this->pdo->prepare(
            'SELECT *
             FROM monthly_plan_routes
             WHERE monthly_plan_id = ?
             ORDER BY id ASC'
        );
        $routeStmt->execute([(int) $plan['id']]);
        $routes = [];
        $childStmt = $this->pdo->prepare(
            'SELECT monthly_plan_route_students.*, users.first_name, users.last_name, students.school_name
             FROM monthly_plan_route_students
             JOIN students ON students.id = monthly_plan_route_students.student_id
             JOIN users ON users.id = students.user_id
             WHERE monthly_plan_route_students.monthly_plan_route_id = ?
             ORDER BY users.first_name ASC, users.last_name ASC'
        );

        foreach ($routeStmt->fetchAll() as $route) {
            $childStmt->execute([(int) $route['id']]);
            $routes[] = [
                'id' => (int) $route['id'],
                'routeId' => (int) $route['id'],
                'destinationKey' => $route['destination_key'],
                'destinationAddress' => $route['destination_address'],
                'destinationLatitude' => $route['destination_latitude'] !== null ? (float) $route['destination_latitude'] : null,
                'destinationLongitude' => $route['destination_longitude'] !== null ? (float) $route['destination_longitude'] : null,
                'childCount' => (int) $route['child_count'],
                'grossDailyAmount' => (float) $route['gross_daily_amount'],
                'discountAmount' => (float) $route['discount_amount'],
                'netDailyAmount' => (float) $route['net_daily_amount'],
                'children' => array_map(fn ($child) => [
                    'studentId' => (int) $child['student_id'],
                    'studentName' => trim($child['first_name'] . ' ' . $child['last_name']),
                    'schoolName' => $child['school_name'],
                    'pickupAddress' => $child['pickup_address'],
                    'pickupLatitude' => $child['pickup_latitude'] !== null ? (float) $child['pickup_latitude'] : null,
                    'pickupLongitude' => $child['pickup_longitude'] !== null ? (float) $child['pickup_longitude'] : null,
                    'distanceKm' => (float) $child['distance_km'],
                    'grossDailyAmount' => (float) $child['gross_daily_amount'],
                    'netDailyAmount' => (float) $child['net_daily_amount'],
                ], $childStmt->fetchAll()),
            ];
        }

        $payment = $this->servicePaymentForPlan((int) $plan['id']);

        return [
            'id' => (int) $plan['id'],
            'planId' => (int) $plan['id'],
            'billingMonth' => substr((string) $plan['billing_month'], 0, 7),
            'monthLabel' => (new \DateTime((string) $plan['billing_month']))->format('F Y'),
            'tripType' => $this->normalizeMonthlyTripType($plan['trip_type'] ?? null),
            'tripTypeLabel' => $this->normalizeMonthlyTripType($plan['trip_type'] ?? null) === 'round_trip' ? 'Round trip' : 'One way',
            'pickupTime' => substr((string) $plan['scheduled_time'], 0, 5),
            'dropoffTime' => substr((string) ($plan['return_time'] ?: $plan['scheduled_time']), 0, 5),
            'status' => $plan['status'],
            'routeCount' => (int) $plan['route_count'],
            'childCount' => (int) $plan['child_count'],
            'serviceDays' => (int) $plan['service_days'],
            'dailyTotal' => (float) $plan['net_daily_total'],
            'refundPerNoClassDay' => round((float) $plan['net_daily_total'] * self::MONTHLY_NO_CLASS_REFUND_RATE, 2),
            'monthlyAmount' => (float) $plan['monthly_amount'],
            'paymentStatus' => $payment['status'] ?? 'pending',
            'paidAt' => $payment['paid_at'] ?? null,
            'routes' => $routes,
        ];
    }

    private function normalizeBillingMonth(?string $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return date('Y-m');
        }

        $timestamp = strtotime(strlen($raw) === 7 ? $raw . '-01' : $raw);

        return $timestamp ? date('Y-m', $timestamp) : date('Y-m');
    }

    private function normalizeBillingDate(?string $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return date('Y-m-d');
        }

        $timestamp = strtotime(strlen($raw) === 7 ? $raw . '-01' : $raw);

        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
    }

    private function normalizeMonthlyTripType(?string $value): string
    {
        $type = strtolower(trim((string) $value));
        $type = str_replace([' ', '-'], '_', $type);

        return in_array($type, ['round_trip', 'roundtrip'], true) ? 'round_trip' : 'one_way';
    }

    private function monthlyServiceDays(string $monthStart): int
    {
        return 30;
    }

    private function monthlyPlanEndDate(string $serviceStartDate): string
    {
        $start = new \DateTime($serviceStartDate);

        for ($day = 0, $serviceDays = 0; $serviceDays < $this->monthlyServiceDays($serviceStartDate); $day++) {
            $date = (clone $start)->modify('+' . $day . ' days');

            if (!$this->isWeekday($date)) {
                continue;
            }

            $serviceDays++;
            $endDate = $date;
        }

        return ($endDate ?? $start)->format('Y-m-d');
    }

    private function isWeekday(\DateTimeInterface $date): bool
    {
        return (int) $date->format('N') <= 5;
    }

    private function servicePaymentForMonth(int $parentId, string $monthStart): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM service_payments WHERE parent_id = ? AND billing_month = ? LIMIT 1');
        $stmt->execute([$parentId, $monthStart]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    private function servicePaymentForPlan(int $planId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM service_payments WHERE monthly_plan_id = ? ORDER BY paid_at DESC, id DESC LIMIT 1');
        $stmt->execute([$planId]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    private function monthlyPlanForMonth(int $parentId, string $monthStart): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM monthly_plans WHERE parent_id = ? AND billing_month = ? AND status = "active" LIMIT 1');
        $stmt->execute([$parentId, $monthStart]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    private function monthlyPlanForRoute(int $parentId, string $monthStart, string $destinationKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM monthly_plans
             WHERE parent_id = ? AND billing_month = ? AND destination_key = ? AND status = "active"
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$parentId, $monthStart, $destinationKey]);
        $plan = $stmt->fetch();

        return $plan ?: null;
    }

    private function nullableFloat($value): ?float
    {
        return trim((string) $value) === '' ? null : (float) $value;
    }

    private function distanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalizeScheduledTime($value): string
    {
        $raw = trim((string) $value);
        $timestamp = strtotime('1970-01-01 ' . ($raw ?: date('H:i:s')));

        return $timestamp ? date('H:i:s', $timestamp) : date('H:i:s');
    }

    private function minutesFromTime(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return ($hour * 60) + $minute;
    }

    private function sameLocation(string $pickupAddress, string $dropoffAddress, ?float $pickupLat, ?float $pickupLng, ?float $dropoffLat, ?float $dropoffLng): bool
    {
        if ($pickupAddress !== '' && $dropoffAddress !== '' && strtolower($pickupAddress) === strtolower($dropoffAddress)) {
            return true;
        }

        return $pickupLat !== null && $pickupLng !== null && $dropoffLat !== null && $dropoffLng !== null
            && abs($pickupLat - $dropoffLat) < 0.00001
            && abs($pickupLng - $dropoffLng) < 0.00001;
    }

    private function dashboardData(array $user): array
    {
        $parentId = $this->parentIdForUser((int) $user['id']);
        $this->ensureBookingMonthlyPlanColumns();
        $monthlyPlan = $this->monthlyPlanEstimate($parentId);
        $this->notifyMonthlyPlanExpirationReminders($parentId, (int) $user['id']);

        return [
            'students' => $this->studentsForParent($parentId),
            'bookings' => $this->bookingsForParent($parentId),
            'rides' => $this->ridesForParent($parentId),
            'billing' => $monthlyPlan,
            'monthlyPlan' => $monthlyPlan,
            'monthlyPlans' => $this->activeMonthlyPlansForParent($parentId),
            'transactions' => $this->transactionsForParent($parentId),
            'notifications' => $this->notificationsForUser((int) $user['id'], $user['role_code']),
            'messages' => $this->messagesForUser((int) $user['id']),
        ];
    }

    private function transactionsForParent(int $parentId): array
    {
        $this->ensureServicePaymentsTable();
        $this->ensureMonthlyPlanRefundsTable();
        $stmt = $this->pdo->prepare(
            'SELECT service_payments.*, monthly_plans.trip_type, monthly_plans.destination_address
             FROM service_payments
             LEFT JOIN monthly_plans ON monthly_plans.id = service_payments.monthly_plan_id
             WHERE service_payments.parent_id = ?
             ORDER BY COALESCE(service_payments.paid_at, service_payments.created_at) DESC, service_payments.id DESC
             LIMIT 100'
        );
        $stmt->execute([$parentId]);
        $payments = array_map(fn ($payment) => [
            'id' => 'payment-' . (int) $payment['id'],
            'type' => 'payment',
            'role' => 'parent',
            'title' => 'Monthly plan payment',
            'description' => ($payment['destination_address'] ?: 'Student transport plan') . ' / ' . (($payment['trip_type'] ?? 'one_way') === 'round_trip' ? 'Round trip' : 'One way'),
            'amount' => (float) $payment['amount_paid'],
            'status' => $payment['status'],
            'method' => $payment['payment_method'],
            'reference' => $payment['reference_number'],
            'date' => $payment['paid_at'] ?: $payment['created_at'],
            'billingMonth' => substr((string) $payment['billing_month'], 0, 7),
            'serviceDays' => (int) $payment['service_days'],
            'routeCount' => (int) $payment['route_count'],
        ], $stmt->fetchAll());

        $refundStmt = $this->pdo->prepare(
            'SELECT monthly_plan_refunds.*, monthly_plans.trip_type, monthly_plans.destination_address
             FROM monthly_plan_refunds
             LEFT JOIN monthly_plans ON monthly_plans.id = monthly_plan_refunds.monthly_plan_id
             WHERE monthly_plan_refunds.parent_id = ?
             ORDER BY COALESCE(monthly_plan_refunds.refunded_at, monthly_plan_refunds.created_at) DESC, monthly_plan_refunds.id DESC
             LIMIT 100'
        );
        $refundStmt->execute([$parentId]);
        $refunds = array_map(fn ($refund) => [
            'id' => 'refund-' . (int) $refund['id'],
            'type' => 'refund',
            'role' => 'parent',
            'title' => 'No-class trip refund',
            'description' => ($refund['destination_address'] ?: 'Monthly plan route') . ' / ' . ($refund['reason'] ?: 'Cancelled daily trip'),
            'amount' => (float) $refund['refund_amount'],
            'status' => $refund['status'],
            'method' => 'monthly plan refund',
            'reference' => $refund['reference_number'],
            'date' => $refund['refunded_at'] ?: $refund['created_at'],
            'scheduledDate' => $refund['scheduled_date'],
        ], $refundStmt->fetchAll());
        $transactions = array_merge($payments, $refunds);
        usort($transactions, fn ($left, $right) => strtotime((string) ($right['date'] ?? '')) <=> strtotime((string) ($left['date'] ?? '')));

        return array_slice($transactions, 0, 100);
    }

    private function studentsForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT students.*, users.first_name, users.last_name, users.email, users.mobile_number
             FROM students
             JOIN users ON users.id = students.user_id
             WHERE students.parent_id = ?
             ORDER BY students.created_at DESC'
        );
        $stmt->execute([$parentId]);

        return array_map(fn ($student) => [
            'id' => (int) $student['id'],
            'userId' => (int) $student['user_id'],
            'name' => trim($student['first_name'] . ' ' . $student['last_name']),
            'email' => $student['email'],
            'mobileNumber' => $student['mobile_number'],
            'lrn' => $student['lrn'],
            'schoolName' => $student['school_name'],
            'gradeLevel' => $student['grade_level'],
            'pickupAddress' => $student['pickup_address'],
            'pickupLatitude' => $student['pickup_latitude'],
            'pickupLongitude' => $student['pickup_longitude'],
            'dropoffAddress' => $student['dropoff_address'],
            'dropoffLatitude' => $student['dropoff_latitude'],
            'dropoffLongitude' => $student['dropoff_longitude'],
            'emergencyContact' => 'Parent account',
            'notes' => $student['medical_notes'],
        ], $stmt->fetchAll());
    }

    private function bookingsForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bookings.*, monthly_plans.billing_month AS monthly_plan_billing_month,
                su.first_name AS student_first_name, su.last_name AS student_last_name,
                du.first_name AS driver_first_name, du.last_name AS driver_last_name, du.profile_photo AS driver_profile_photo,
                drivers.license_number AS driver_license_number, drivers.license_expiry AS driver_license_expiry,
                drivers.license_photo_path AS driver_license_photo_path, drivers.vehicle_model AS driver_vehicle_model,
                drivers.vehicle_plate_number AS driver_vehicle_plate_number, drivers.vehicle_color AS driver_vehicle_color,
                drivers.vehicle_photo_path AS driver_vehicle_photo_path, drivers.approval_status AS driver_approval_status,
                drivers.is_online AS driver_is_online, vehicles.capacity AS driver_vehicle_capacity, vehicles.registration_path AS driver_vehicle_orcr_path
             FROM bookings
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             LEFT JOIN monthly_plans ON monthly_plans.id = bookings.monthly_plan_id
             LEFT JOIN drivers ON drivers.id = bookings.assigned_driver_id
             LEFT JOIN users du ON du.id = drivers.user_id
             LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
             WHERE bookings.parent_id = ?
               AND (
                    bookings.monthly_plan_id IS NULL
                    OR bookings.scheduled_date >= monthly_plans.billing_month
               )
             ORDER BY COALESCE(monthly_plans.billing_month, bookings.scheduled_date) DESC,
                bookings.scheduled_date ASC,
                COALESCE(bookings.adjusted_pickup_time, bookings.pickup_time, bookings.scheduled_time) ASC,
                bookings.id ASC'
        );
        $stmt->execute([$parentId]);

        return array_map(fn ($booking) => [
            'id' => (int) $booking['id'],
            'studentId' => (int) $booking['student_id'],
            'studentName' => trim($booking['student_first_name'] . ' ' . $booking['student_last_name']),
            'pickupAddress' => $booking['pickup_address'],
            'pickupLatitude' => $booking['pickup_latitude'],
            'pickupLongitude' => $booking['pickup_longitude'],
            'dropoffAddress' => $booking['dropoff_address'],
            'dropoffLatitude' => $booking['dropoff_latitude'],
            'dropoffLongitude' => $booking['dropoff_longitude'],
            'scheduledDate' => $booking['scheduled_date'],
            'scheduledTime' => substr((string) ($booking['adjusted_pickup_time'] ?: $booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5),
            'pickupTime' => substr((string) ($booking['adjusted_pickup_time'] ?: $booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5),
            'requestedPickupTime' => substr((string) ($booking['pickup_time'] ?: $booking['scheduled_time']), 0, 5),
            'dropoffTime' => !empty($booking['dropoff_time']) ? substr((string) $booking['dropoff_time'], 0, 5) : '',
            'adjustedPickupTime' => !empty($booking['adjusted_pickup_time']) ? substr((string) $booking['adjusted_pickup_time'], 0, 5) : null,
            'tripType' => $booking['trip_type'],
            'routeDirection' => str_contains(strtolower((string) ($booking['notes'] ?? '')), 'return') ? 'return_home' : 'to_school',
            'recurringGroupId' => $booking['recurring_group_id'] ?? null,
            'monthlyPlanId' => !empty($booking['monthly_plan_id']) ? (int) $booking['monthly_plan_id'] : null,
            'monthlyPlanRouteId' => !empty($booking['monthly_plan_route_id']) ? (int) $booking['monthly_plan_route_id'] : null,
            'monthlyPlanStartDate' => $booking['monthly_plan_billing_month'] ?? null,
            'monthlyPlanEndDate' => !empty($booking['monthly_plan_billing_month']) ? $this->monthlyPlanEndDate((string) $booking['monthly_plan_billing_month']) : null,
            'driverPayoutAmount' => (float) ($booking['driver_payout_amount'] ?? 0),
            'status' => $booking['booking_status'],
            'driverId' => $booking['assigned_driver_id'] ? (int) $booking['assigned_driver_id'] : null,
            'driverName' => trim(($booking['driver_first_name'] ?? '') . ' ' . ($booking['driver_last_name'] ?? '')) ?: 'To be assigned',
            'driver' => !empty($booking['assigned_driver_id']) ? [
                'id' => (int) $booking['assigned_driver_id'],
                'name' => trim(($booking['driver_first_name'] ?? '') . ' ' . ($booking['driver_last_name'] ?? '')) ?: 'Assigned driver',
                'profilePhotoUrl' => $this->fileUrl($booking['driver_profile_photo'] ?? null),
                'vehicle' => trim(($booking['driver_vehicle_model'] ?? '') . ' - ' . ($booking['driver_vehicle_plate_number'] ?? '')),
                'vehicleModel' => $booking['driver_vehicle_model'] ?? '',
                'vehiclePlateNumber' => $booking['driver_vehicle_plate_number'] ?? '',
                'vehicleColor' => $booking['driver_vehicle_color'] ?? '',
                'vehicleCapacity' => (int) ($booking['driver_vehicle_capacity'] ?? 1),
                'licenseNumber' => $booking['driver_license_number'] ?? '',
                'licenseExpiry' => $booking['driver_license_expiry'] ?? '',
                'licensePhotoUrl' => $this->fileUrl($booking['driver_license_photo_path'] ?? null),
                'vehiclePhotoUrl' => $this->fileUrl($booking['driver_vehicle_photo_path'] ?? null),
                'vehicleOrcrUrl' => $this->fileUrl($booking['driver_vehicle_orcr_path'] ?? null),
                'approvalStatus' => $booking['driver_approval_status'] ?? 'approved',
                'isOnline' => (bool) ($booking['driver_is_online'] ?? false),
            ] : null,
        ], $stmt->fetchAll());
    }

    private function notifyMonthlyPlanExpirationReminders(int $parentId, int $userId): void
    {
        $this->ensureMonthlyPlanTables();
        $stmt = $this->pdo->prepare(
            'SELECT id, billing_month, destination_address, child_count, route_count
             FROM monthly_plans
             WHERE parent_id = ?
               AND status = "active"
             ORDER BY billing_month ASC, id ASC'
        );
        $stmt->execute([$parentId]);

        $existing = $this->pdo->prepare(
            'SELECT id
             FROM notifications
             WHERE user_id = ?
               AND type = ?
               AND reference_id = ?
             LIMIT 1'
        );

        foreach ($stmt->fetchAll() as $plan) {
            $endDate = $this->monthlyPlanEndDate((string) $plan['billing_month']);
            $days = (int) (new \DateTime(date('Y-m-d')))->diff(new \DateTime($endDate))->format('%r%a');

            if (!in_array($days, [7, 3, 2, 1], true)) {
                continue;
            }

            $type = 'monthly_plan_expiration_' . $days . '_days';
            $existing->execute([$userId, $type, (int) $plan['id']]);

            if ($existing->fetchColumn()) {
                continue;
            }

            $month = new \DateTime((string) $plan['billing_month']);
            $expiresAt = (new \DateTime($endDate))->format('F j, Y');
            $monthLabel = $month->format('F Y');
            $destination = trim((string) ($plan['destination_address'] ?? ''));
            $routeLabel = $destination !== '' ? $destination : 'your child transport route';
            $childCount = (int) $plan['child_count'];
            $routeCount = (int) $plan['route_count'];
            $coverage = $childCount . ' child' . ($childCount === 1 ? '' : 'ren') . ' / ' . $routeCount . ' route' . ($routeCount === 1 ? '' : 's');
            $title = $days === 1 ? 'Monthly plan expires tomorrow' : 'Monthly plan expires in ' . $days . ' days';
            $body = 'Your ' . $monthLabel . ' monthly plan for ' . $routeLabel . ' is active and expires on ' . $expiresAt . '. Coverage: ' . $coverage . '. Renew before expiration to keep daily routes available.';

            $this->notifyUser($userId, $title, $body, $type, (int) $plan['id']);
        }
    }

    private function ridesForParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rides.*, bookings.pickup_address, bookings.dropoff_address,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                bookings.scheduled_date, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time, bookings.adjusted_pickup_time, bookings.trip_type, bookings.recurring_group_id,
                bookings.notes, bookings.monthly_plan_id, bookings.monthly_plan_route_id, bookings.driver_payout_amount,
                su.id AS student_user_id, su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.id AS parent_user_id, pu.first_name AS parent_first_name, pu.last_name AS parent_last_name,
                du.id AS driver_user_id, du.first_name AS driver_first_name, du.last_name AS driver_last_name, du.profile_photo AS driver_profile_photo,
                drivers.license_number AS driver_license_number, drivers.license_expiry AS driver_license_expiry,
                drivers.license_photo_path AS driver_license_photo_path, drivers.vehicle_model, drivers.vehicle_plate_number,
                drivers.vehicle_color, drivers.vehicle_photo_path, drivers.approval_status, drivers.is_online,
                drivers.current_latitude, drivers.current_longitude, vehicles.capacity AS vehicle_capacity, vehicles.registration_path AS vehicle_orcr_path
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN drivers ON drivers.id = rides.driver_id
             JOIN users du ON du.id = drivers.user_id
             LEFT JOIN vehicles ON vehicles.driver_id = drivers.id
              WHERE bookings.parent_id = ?
                AND bookings.scheduled_date = CURDATE()
                AND rides.ride_status NOT IN ("completed", "cancelled")
                AND bookings.booking_status NOT IN ("completed", "cancelled")
              ORDER BY rides.updated_at DESC'
        );
        $stmt->execute([$parentId]);

        return array_map([$this, 'rideResource'], $stmt->fetchAll());
    }

    private function notificationsForUser(int $userId, string $role): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
        $stmt->execute([$userId]);
        return array_map(fn ($item) => [
            'id' => (int) $item['id'],
            'role' => $role,
            'title' => $item['title'],
            'body' => $item['body'],
            'time' => $item['created_at'],
        ], $stmt->fetchAll());
    }

    private function messagesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT messages.*, users.first_name, users.last_name, roles.code AS sender_role,
                receiver_users.first_name AS receiver_first_name, receiver_users.last_name AS receiver_last_name,
                receiver_roles.code AS receiver_role
             FROM messages
             JOIN users ON users.id = messages.sender_user_id
             JOIN roles ON roles.id = users.role_id
             JOIN users receiver_users ON receiver_users.id = messages.receiver_user_id
             JOIN roles receiver_roles ON receiver_roles.id = receiver_users.role_id
             WHERE messages.sender_user_id = ? OR messages.receiver_user_id = ?
             ORDER BY messages.created_at ASC'
        );
        $stmt->execute([$userId, $userId]);
        return array_map(fn ($message) => $this->messageResource($message), $stmt->fetchAll());
    }

    private function rideResource(array $ride): array
    {
        $hasDriverLocation = $ride['current_latitude'] !== null && $ride['current_latitude'] !== ''
            && $ride['current_longitude'] !== null && $ride['current_longitude'] !== '';
        $driverLat = $hasDriverLocation ? (float) $ride['current_latitude'] : null;
        $driverLng = $hasDriverLocation ? (float) $ride['current_longitude'] : null;
        $pickupLat = (float) ($ride['pickup_latitude'] ?: 10.676344);
        $pickupLng = (float) ($ride['pickup_longitude'] ?: 122.953221);
        $dropoffLat = (float) ($ride['dropoff_latitude'] ?: 10.668364);
        $dropoffLng = (float) ($ride['dropoff_longitude'] ?: 123.019768);
        $carpoolStops = $this->carpoolStopsForRide($ride);
        $routePoints = array_map(fn ($stop) => $stop['pickupLocation'], $carpoolStops);

        if (!$routePoints) {
            $routePoints[] = ['latitude' => $pickupLat, 'longitude' => $pickupLng];
        }

        $routePoints[] = ['latitude' => $dropoffLat, 'longitude' => $dropoffLng];
        $orderedStops = $this->orderedStopsForRide($ride, $carpoolStops, $driverLat, $driverLng);

        return [
            'id' => (int) $ride['id'],
            'bookingId' => (int) $ride['booking_id'],
            'studentUserId' => (int) $ride['student_user_id'],
            'parentUserId' => (int) $ride['parent_user_id'],
            'driverUserId' => (int) $ride['driver_user_id'],
            'studentName' => trim($ride['student_first_name'] . ' ' . $ride['student_last_name']),
            'parentName' => trim($ride['parent_first_name'] . ' ' . $ride['parent_last_name']),
            'driverName' => trim($ride['driver_first_name'] . ' ' . $ride['driver_last_name']),
            'vehicle' => trim($ride['vehicle_model'] . ' - ' . $ride['vehicle_plate_number']),
            'vehicleModel' => $ride['vehicle_model'],
            'vehiclePlateNumber' => $ride['vehicle_plate_number'],
            'vehicleColor' => $ride['vehicle_color'] ?? '',
            'vehicleCapacity' => (int) ($ride['vehicle_capacity'] ?? 1),
            'driverProfilePhotoUrl' => $this->fileUrl($ride['driver_profile_photo'] ?? null),
            'driverLicenseNumber' => $ride['driver_license_number'] ?? '',
            'driverLicenseExpiry' => $ride['driver_license_expiry'] ?? '',
            'driverLicensePhotoUrl' => $this->fileUrl($ride['driver_license_photo_path'] ?? null),
            'driverApprovalStatus' => $ride['approval_status'] ?? 'approved',
            'driverIsOnline' => (bool) ($ride['is_online'] ?? false),
            'vehiclePhotoUrl' => $this->fileUrl($ride['vehicle_photo_path'] ?? null),
            'vehicleOrcrUrl' => $this->fileUrl($ride['vehicle_orcr_path'] ?? null),
            'driver' => [
                'id' => (int) ($ride['driver_id'] ?? 0),
                'name' => trim($ride['driver_first_name'] . ' ' . $ride['driver_last_name']),
                'profilePhotoUrl' => $this->fileUrl($ride['driver_profile_photo'] ?? null),
                'vehicle' => trim($ride['vehicle_model'] . ' - ' . $ride['vehicle_plate_number']),
                'vehicleModel' => $ride['vehicle_model'],
                'vehiclePlateNumber' => $ride['vehicle_plate_number'],
                'vehicleColor' => $ride['vehicle_color'] ?? '',
                'vehicleCapacity' => (int) ($ride['vehicle_capacity'] ?? 1),
                'licenseNumber' => $ride['driver_license_number'] ?? '',
                'licenseExpiry' => $ride['driver_license_expiry'] ?? '',
                'licensePhotoUrl' => $this->fileUrl($ride['driver_license_photo_path'] ?? null),
                'vehiclePhotoUrl' => $this->fileUrl($ride['vehicle_photo_path'] ?? null),
                'vehicleOrcrUrl' => $this->fileUrl($ride['vehicle_orcr_path'] ?? null),
                'approvalStatus' => $ride['approval_status'] ?? 'approved',
                'isOnline' => (bool) ($ride['is_online'] ?? false),
            ],
            'status' => ucwords(str_replace('_', ' ', $ride['ride_status'])),
            'pickupAddress' => $ride['pickup_address'],
            'dropoffAddress' => $ride['dropoff_address'],
            'scheduledDate' => $ride['scheduled_date'] ?? '',
            'scheduledTime' => isset($ride['scheduled_time']) ? substr((string) ($ride['adjusted_pickup_time'] ?: $ride['pickup_time'] ?: $ride['scheduled_time']), 0, 5) : '',
            'requestedPickupTime' => isset($ride['scheduled_time']) ? substr((string) ($ride['pickup_time'] ?: $ride['scheduled_time']), 0, 5) : '',
            'adjustedPickupTime' => !empty($ride['adjusted_pickup_time']) ? substr((string) $ride['adjusted_pickup_time'], 0, 5) : null,
            'plannedDropoffTime' => !empty($ride['dropoff_time']) ? substr((string) $ride['dropoff_time'], 0, 5) : '',
            'tripType' => $ride['trip_type'] ?? 'recurring',
            'routeDirection' => str_contains(strtolower((string) ($ride['notes'] ?? '')), 'return') ? 'return_home' : 'to_school',
            'recurringGroupId' => $ride['recurring_group_id'] ?? null,
            'monthlyPlanId' => !empty($ride['monthly_plan_id']) ? (int) $ride['monthly_plan_id'] : null,
            'monthlyPlanRouteId' => !empty($ride['monthly_plan_route_id']) ? (int) $ride['monthly_plan_route_id'] : null,
            'driverPayoutAmount' => (float) ($ride['driver_payout_amount'] ?? 0),
            'etaMinutes' => 0,
            'distanceKm' => 0,
            'pickupTime' => $ride['started_at'] ?: '',
            'dropoffTime' => $ride['dropped_off_at'] ?: '',
            'dropoffPhotoUrl' => $this->fileUrl($ride['dropoff_photo_path'] ?? null),
            'progress' => $ride['ride_status'] === 'completed' ? 1 : 0,
            'currentPointIndex' => 0,
            'isTracking' => !empty($ride['started_at']) && empty($ride['completed_at']),
            'hasDriverLocation' => $hasDriverLocation,
            'location' => $hasDriverLocation ? [
                'latitude' => $driverLat,
                'longitude' => $driverLng,
                'latitudeDelta' => 0.03,
                'longitudeDelta' => 0.03,
            ] : null,
            'pickupLocation' => ['latitude' => $pickupLat, 'longitude' => $pickupLng],
            'dropoffLocation' => ['latitude' => $dropoffLat, 'longitude' => $dropoffLng],
            'carpoolStops' => $carpoolStops,
            'orderedStops' => $orderedStops,
            'routePoints' => $routePoints,
        ];
    }

    private function dropoffMatchSql(array $source, array &$params): string
    {
        $address = trim((string) ($source['dropoff_address'] ?? ''));
        $hasCoordinates = ($source['dropoff_latitude'] ?? null) !== null && ($source['dropoff_latitude'] ?? '') !== ''
            && ($source['dropoff_longitude'] ?? null) !== null && ($source['dropoff_longitude'] ?? '') !== '';

        if ($hasCoordinates) {
            $params[] = (float) $source['dropoff_latitude'];
            $params[] = (float) $source['dropoff_longitude'];
            $params[] = $address;

            return '(
                (
                    bookings.dropoff_latitude IS NOT NULL
                    AND bookings.dropoff_longitude IS NOT NULL
                    AND ABS(bookings.dropoff_latitude - ?) < 0.0001
                    AND ABS(bookings.dropoff_longitude - ?) < 0.0001
                )
                OR LOWER(TRIM(bookings.dropoff_address)) = LOWER(TRIM(?))
            )';
        }

        $params[] = $address;

        return 'LOWER(TRIM(bookings.dropoff_address)) = LOWER(TRIM(?))';
    }

    private function routeDirectionMatchSql(array $source, array &$params): string
    {
        $direction = str_contains(strtolower((string) ($source['notes'] ?? '')), 'return') ? 'return_home' : 'to_school';
        $params[] = $direction;
        $params[] = $direction;

        return '(
            (? = "return_home" AND LOWER(COALESCE(bookings.notes, "")) LIKE "%return%")
            OR (? = "to_school" AND LOWER(COALESCE(bookings.notes, "")) NOT LIKE "%return%")
        )';
    }

    private function carpoolStopsForRide(array $ride): array
    {
        if (empty($ride['driver_id']) || empty($ride['scheduled_time']) || empty($ride['scheduled_date'])) {
            return [];
        }

        $params = [
            (int) $ride['driver_id'],
            $ride['scheduled_date'],
            $ride['pickup_time'] ?: $ride['scheduled_time'],
        ];
        $routeDirectionSql = $this->routeDirectionMatchSql($ride, $params);
        $stmt = $this->pdo->prepare(
            'SELECT rides.id AS ride_id, rides.ride_status, rides.picked_up_at, rides.dropped_off_at, rides.completed_at,
                bookings.id AS booking_id, bookings.pickup_address, bookings.dropoff_address, bookings.scheduled_time, bookings.pickup_time, bookings.dropoff_time, bookings.adjusted_pickup_time,
                bookings.pickup_latitude, bookings.pickup_longitude, bookings.dropoff_latitude, bookings.dropoff_longitude,
                su.first_name AS student_first_name, su.last_name AS student_last_name,
                pu.first_name AS parent_first_name, pu.last_name AS parent_last_name
             FROM rides
             JOIN bookings ON bookings.id = rides.booking_id
             JOIN students ON students.id = bookings.student_id
             JOIN users su ON su.id = students.user_id
             JOIN parents ON parents.id = bookings.parent_id
             JOIN users pu ON pu.id = parents.user_id
             WHERE rides.driver_id = ?
               AND bookings.scheduled_date = ?
               AND COALESCE(bookings.pickup_time, bookings.scheduled_time) = ?
               AND bookings.booking_status NOT IN ("cancelled")
               AND (rides.completed_at IS NULL OR rides.completed_at >= (NOW() - INTERVAL 1 DAY))
               AND ' . $routeDirectionSql . '
             ORDER BY bookings.scheduled_time ASC, bookings.id ASC'
        );
        $stmt->execute($params);

        $stops = [];

        foreach ($stmt->fetchAll() as $index => $stop) {
            $stops[] = [
                'rideId' => (int) $stop['ride_id'],
                'bookingId' => (int) $stop['booking_id'],
                'studentName' => trim($stop['student_first_name'] . ' ' . $stop['student_last_name']),
                'parentName' => trim($stop['parent_first_name'] . ' ' . $stop['parent_last_name']),
                'pickupAddress' => $stop['pickup_address'],
                'dropoffAddress' => $stop['dropoff_address'],
                'expectedPickupTime' => substr((string) ($stop['adjusted_pickup_time'] ?: $this->timeWithOffset((string) ($stop['pickup_time'] ?: $stop['scheduled_time']), $index * 4)), 0, 5),
                'status' => ucwords(str_replace('_', ' ', (string) $stop['ride_status'])),
                'rawStatus' => $stop['ride_status'],
                'pickupTime' => $stop['picked_up_at'] ?: '',
                'dropoffTime' => $stop['dropped_off_at'] ?: ($stop['completed_at'] ?: ''),
                'plannedDropoffTime' => !empty($stop['dropoff_time']) ? substr((string) $stop['dropoff_time'], 0, 5) : '',
                'pickupLocation' => [
                    'latitude' => (float) ($stop['pickup_latitude'] ?: 10.676344),
                    'longitude' => (float) ($stop['pickup_longitude'] ?: 122.953221),
                ],
                'dropoffLocation' => [
                    'latitude' => (float) ($stop['dropoff_latitude'] ?: 10.668364),
                    'longitude' => (float) ($stop['dropoff_longitude'] ?: 123.019768),
                ],
            ];
        }

        return $stops;
    }

    private function orderedStopsForRide(array $ride, array $pickupStops, ?float $driverLat, ?float $driverLng): array
    {
        $scheduledTime = (string) (($ride['pickup_time'] ?? null) ?: ($ride['scheduled_time'] ?? '07:00:00'));
        $stops = [];
        $passengers = 0;

        foreach ($pickupStops as $index => $stop) {
            $passengers++;
            $stops[] = [
                'id' => 'pickup-' . $stop['rideId'],
                'type' => 'pickup',
                'rideId' => $stop['rideId'],
                'bookingId' => $stop['bookingId'],
                'studentName' => $stop['studentName'],
                'parentName' => $stop['parentName'],
                'address' => $stop['pickupAddress'],
                'location' => $stop['pickupLocation'],
                'expectedTime' => $stop['expectedPickupTime'] ?? $this->timeWithOffset($scheduledTime, $index * 4),
                'etaMinutes' => $this->stopEtaMinutes($driverLat, $driverLng, $stop['pickupLocation'], $index * 4),
                'passengerCount' => $passengers,
                'status' => $this->pickupStopStatus((string) ($stop['rawStatus'] ?? 'assigned')),
                'rawStatus' => $stop['rawStatus'] ?? 'assigned',
            ];
        }

        foreach ($pickupStops as $index => $stop) {
            $passengers = max(0, $passengers - 1);
            $dropoffLocation = $stop['dropoffLocation'] ?? ['latitude' => (float) ($ride['dropoff_latitude'] ?: 10.668364), 'longitude' => (float) ($ride['dropoff_longitude'] ?: 123.019768)];
            $stops[] = [
                'id' => 'dropoff-' . $stop['rideId'],
                'type' => 'dropoff',
                'rideId' => $stop['rideId'],
                'bookingId' => $stop['bookingId'],
                'studentName' => $stop['studentName'],
                'parentName' => $stop['parentName'],
                'address' => $stop['dropoffAddress'] ?? ($ride['dropoff_address'] ?? ''),
                'location' => $dropoffLocation,
                'expectedTime' => !empty($stop['plannedDropoffTime']) ? $stop['plannedDropoffTime'] : $this->timeWithOffset($scheduledTime, (count($pickupStops) * 4) + 12 + ($index * 2)),
                'etaMinutes' => $this->stopEtaMinutes($driverLat, $driverLng, $dropoffLocation, (count($pickupStops) * 4) + 12 + ($index * 2)),
                'passengerCount' => $passengers,
                'status' => $this->dropoffStopStatus((string) ($stop['rawStatus'] ?? 'assigned')),
                'rawStatus' => $stop['rawStatus'] ?? 'assigned',
            ];
        }

        return $stops;
    }

    private function stopEtaMinutes(?float $driverLat, ?float $driverLng, ?array $location, int $fallbackMinutes): int
    {
        if ($driverLat !== null && $driverLng !== null && $location) {
            return max(1, (int) ceil(($this->distanceKm($driverLat, $driverLng, (float) $location['latitude'], (float) $location['longitude']) / 25) * 60));
        }

        return max(1, $fallbackMinutes);
    }

    private function timeWithOffset(string $time, int $minutes): string
    {
        $timestamp = strtotime('1970-01-01 ' . $time) ?: strtotime('1970-01-01 07:00:00');

        return date('H:i', $timestamp + ($minutes * 60));
    }

    private function pickupStopStatus(string $status): string
    {
        return in_array($status, ['picked_up', 'in_transit', 'dropped_off', 'completed'], true) ? 'Boarded' : 'Waiting';
    }

    private function dropoffStopStatus(string $status): string
    {
        if (in_array($status, ['dropped_off', 'completed'], true)) {
            return 'Dropped Off';
        }

        return in_array($status, ['picked_up', 'in_transit'], true) ? 'On Board' : 'Pending Pickup';
    }
}

