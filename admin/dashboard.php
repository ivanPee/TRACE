<?php
$stats = [
    'Total Users' => 128,
    'Active Rides' => 14,
    'Pending Drivers' => 6,
    'Unread Alerts' => 3,
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRACE Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container">
            <span class="navbar-brand">TRACE Admin</span>
        </div>
    </nav>

    <main class="container py-4">
        <div class="row g-3 mb-4">
            <?php foreach ($stats as $label => $value): ?>
                <div class="col-md-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1"><?= htmlspecialchars($label) ?></p>
                            <h2 class="mb-0"><?= htmlspecialchars((string) $value) ?></h2>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">Live Ride Monitoring</div>
                    <div class="card-body">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Ride ID</th>
                                    <th>Driver</th>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>ETA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#1001</td>
                                    <td>Juan Dela Cruz</td>
                                    <td>Ana Cruz</td>
                                    <td><span class="badge text-bg-warning">Driver Arriving</span></td>
                                    <td>8 mins</td>
                                </tr>
                                <tr>
                                    <td>#1002</td>
                                    <td>Mark Ramos</td>
                                    <td>Paolo Ramos</td>
                                    <td><span class="badge text-bg-success">In Transit</span></td>
                                    <td>15 mins</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">Pending Verifications</div>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">Driver: Maria Gomez</div>
                        <div class="list-group-item">Driver: Leo Tan</div>
                        <div class="list-group-item">Parent: Olivia Santos</div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header">Recent Admin Logs</div>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item">Approved driver account #42</div>
                        <div class="list-group-item">Assigned ride #1001</div>
                        <div class="list-group-item">Suspended parent account #18</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

