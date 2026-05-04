<?php
$stats = [
    [
        'label' => 'Total Users',
        'value' => 128,
        'note' => 'Parents, riders, students',
        'color' => 'primary',
    ],
    [
        'label' => 'Active Rides',
        'value' => 14,
        'note' => 'Currently being tracked',
        'color' => 'success',
    ],
    [
        'label' => 'Pending Riders',
        'value' => 6,
        'note' => 'Waiting for approval',
        'color' => 'warning',
    ],
    [
        'label' => 'Unread Alerts',
        'value' => 3,
        'note' => 'Needs admin review',
        'color' => 'danger',
    ],
];

$navItems = [
    ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2', 'active' => true],
    ['label' => 'Riders', 'href' => '#riders', 'icon' => 'bi-person-badge'],
    ['label' => 'Parents', 'href' => '#parents', 'icon' => 'bi-people'],
    ['label' => 'Students', 'href' => '#students', 'icon' => 'bi-mortarboard'],
    ['label' => 'Bookings', 'href' => '#bookings', 'icon' => 'bi-calendar-check'],
    ['label' => 'Live Tracking', 'href' => '#tracking', 'icon' => 'bi-geo-alt'],
    ['label' => 'Vehicles', 'href' => '#vehicles', 'icon' => 'bi-truck'],
    ['label' => 'Payments', 'href' => '#payments', 'icon' => 'bi-credit-card'],
    ['label' => 'Messages', 'href' => '#messages', 'icon' => 'bi-chat-dots'],
    ['label' => 'Reports', 'href' => '#reports', 'icon' => 'bi-bar-chart'],
    ['label' => 'Settings', 'href' => '#settings', 'icon' => 'bi-gear'],
];

$rides = [
    ['id' => '#1001', 'rider' => 'Juan Dela Cruz', 'student' => 'Ana Cruz', 'route' => 'Home to St. Mary School', 'status' => 'Driver Arriving', 'badge' => 'warning', 'eta' => '8 mins'],
    ['id' => '#1002', 'rider' => 'Mark Ramos', 'student' => 'Paolo Ramos', 'route' => 'Rizal Ave. to Central High', 'status' => 'In Transit', 'badge' => 'success', 'eta' => '15 mins'],
    ['id' => '#1003', 'rider' => 'Leo Tan', 'student' => 'Mika Santos', 'route' => 'Campus pickup to Mabini St.', 'status' => 'Assigned', 'badge' => 'primary', 'eta' => '22 mins'],
];

$verifications = [
    ['name' => 'Maria Gomez', 'role' => 'Rider', 'detail' => 'License and vehicle documents'],
    ['name' => 'Leo Tan', 'role' => 'Rider', 'detail' => 'Plate number confirmation'],
    ['name' => 'Olivia Santos', 'role' => 'Parent', 'detail' => 'Valid ID review'],
];

$logs = [
    ['action' => 'Approved rider account #42', 'time' => '10 min ago'],
    ['action' => 'Assigned ride #1001 to Juan Dela Cruz', 'time' => '18 min ago'],
    ['action' => 'Suspended parent account #18', 'time' => '1 hr ago'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRACE Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body>
    <?php
    function renderSidebar(array $navItems): void
    {
        ?>
        <div class="sidebar-brand">
            <div class="brand-mark">T</div>
            <div>
                <div class="fw-bold">TRACE</div>
                <small class="text-secondary">Admin Panel</small>
            </div>
        </div>

        <nav class="nav flex-column sidebar-nav">
            <?php foreach ($navItems as $item): ?>
                <a class="nav-link <?= !empty($item['active']) ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="small text-secondary mb-2">System health</div>
            <div class="d-flex align-items-center justify-content-between">
                <span class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
                <span class="small text-secondary">v1.0</span>
            </div>
        </div>
        <?php
    }
    ?>

    <aside class="admin-sidebar d-none d-lg-flex">
        <?php renderSidebar($navItems); ?>
    </aside>

    <div class="offcanvas offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="adminSidebarLabel">TRACE Admin</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <?php renderSidebar($navItems); ?>
        </div>
    </div>

    <div class="admin-shell">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h1 class="h4 mb-0">Dashboard</h1>
                    <div class="text-secondary small">Monitor trips, account approvals, and daily operations.</div>
                </div>
            </div>

            <div class="topbar-actions">
                <div class="input-group search-box d-none d-md-flex">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input class="form-control" type="search" placeholder="Search records">
                </div>
                <button class="btn btn-light position-relative" type="button" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">3</span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Admin
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="#settings">Profile settings</a></li>
                        <li><a class="dropdown-item" href="#reports">Activity logs</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="login.php">Sign out</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="admin-content">
            <div class="row g-3 mb-4">
                <?php foreach ($stats as $stat): ?>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="text-secondary small fw-semibold"><?= htmlspecialchars($stat['label']) ?></span>
                                    <span class="stat-icon bg-<?= htmlspecialchars($stat['color']) ?>-subtle text-<?= htmlspecialchars($stat['color']) ?>">
                                        <i class="bi bi-circle-fill"></i>
                                    </span>
                                </div>
                                <h2 class="mb-1"><?= htmlspecialchars((string) $stat['value']) ?></h2>
                                <p class="text-secondary small mb-0"><?= htmlspecialchars($stat['note']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm" id="tracking">
                        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h2 class="h6 mb-0">Live Ride Monitoring</h2>
                                <span class="text-secondary small">Active bookings and rider movement status</span>
                            </div>
                            <button class="btn btn-sm btn-primary" type="button">
                                <i class="bi bi-plus-lg me-1"></i>Assign ride
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ride ID</th>
                                            <th>Rider</th>
                                            <th>Student</th>
                                            <th>Route</th>
                                            <th>Status</th>
                                            <th class="text-end">ETA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rides as $ride): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= htmlspecialchars($ride['id']) ?></td>
                                                <td><?= htmlspecialchars($ride['rider']) ?></td>
                                                <td><?= htmlspecialchars($ride['student']) ?></td>
                                                <td class="text-secondary"><?= htmlspecialchars($ride['route']) ?></td>
                                                <td><span class="badge text-bg-<?= htmlspecialchars($ride['badge']) ?>"><?= htmlspecialchars($ride['status']) ?></span></td>
                                                <td class="text-end"><?= htmlspecialchars($ride['eta']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4" id="riders">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Pending Verifications</h2>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($verifications as $verification): ?>
                                <div class="list-group-item py-3">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($verification['name']) ?></div>
                                            <div class="text-secondary small"><?= htmlspecialchars($verification['detail']) ?></div>
                                        </div>
                                        <span class="badge text-bg-light border"><?= htmlspecialchars($verification['role']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm" id="reports">
                        <div class="card-header bg-white">
                            <h2 class="h6 mb-0">Recent Admin Logs</h2>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($logs as $log): ?>
                                <div class="list-group-item py-3">
                                    <div class="fw-semibold"><?= htmlspecialchars($log['action']) ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars($log['time']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-md-6 col-xl-3" id="parents">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="section-icon bg-info-subtle text-info"><i class="bi bi-people"></i></div>
                            <h2 class="h6 mt-3">Parent Accounts</h2>
                            <p class="text-secondary small mb-3">Review guardians, emergency contacts, and student links.</p>
                            <a href="#parents" class="btn btn-sm btn-outline-info">Manage parents</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" id="students">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="section-icon bg-primary-subtle text-primary"><i class="bi bi-mortarboard"></i></div>
                            <h2 class="h6 mt-3">Students</h2>
                            <p class="text-secondary small mb-3">Maintain school details, pickup points, and status.</p>
                            <a href="#students" class="btn btn-sm btn-outline-primary">View students</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" id="bookings">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="section-icon bg-success-subtle text-success"><i class="bi bi-calendar-check"></i></div>
                            <h2 class="h6 mt-3">Bookings</h2>
                            <p class="text-secondary small mb-3">Approve, assign, and monitor scheduled trips.</p>
                            <a href="#bookings" class="btn btn-sm btn-outline-success">Open bookings</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3" id="vehicles">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="section-icon bg-warning-subtle text-warning"><i class="bi bi-truck"></i></div>
                            <h2 class="h6 mt-3">Vehicles</h2>
                            <p class="text-secondary small mb-3">Track plate numbers, capacity, and maintenance status.</p>
                            <a href="#vehicles" class="btn btn-sm btn-outline-warning">Inspect fleet</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="visually-hidden" aria-hidden="true">
                <span id="payments"></span>
                <span id="messages"></span>
                <span id="settings"></span>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

