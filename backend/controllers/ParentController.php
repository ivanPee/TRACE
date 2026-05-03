<?php

namespace Controllers;

use Core\Request;
use Core\Response;

class ParentController
{
    public function createStudent(): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Student creation endpoint scaffolded.',
            'data' => [
                'lrn' => $input['lrn'] ?? null,
                'student_name' => $input['student_name'] ?? null,
            ],
        ], 201);
    }

    public function createBooking(): void
    {
        $input = Request::input();

        Response::json([
            'success' => true,
            'message' => 'Booking endpoint scaffolded.',
            'data' => [
                'student_id' => $input['student_id'] ?? null,
                'scheduled_date' => $input['scheduled_date'] ?? null,
                'scheduled_time' => $input['scheduled_time'] ?? null,
            ],
        ], 201);
    }
}

