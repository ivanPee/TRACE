<?php

require_once __DIR__ . '/bootstrap.php';

function admin_nav_items($active)
{
    $counts = admin_nav_counts();
    $items = [
        ['section' => 'Operations'],
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2'],
        ['key' => 'bookings', 'label' => 'Monthly Plans', 'href' => 'bookings.php', 'icon' => 'bi-calendar-check', 'count' => $counts['activeSubscriptions'] ?? null],
        ['key' => 'refunds', 'label' => 'Refunds', 'href' => 'refunds.php', 'icon' => 'bi-arrow-counterclockwise', 'count' => $counts['pendingRefunds'] ?? null],
        ['key' => 'drivers', 'label' => 'Drivers', 'href' => 'drivers.php', 'icon' => 'bi-person-badge', 'count' => $counts['pendingDrivers'] ?? null],
        ['key' => 'vehicles', 'label' => 'Vehicles', 'href' => 'vehicles.php', 'icon' => 'bi-truck'],
        ['section' => 'Accounts'],
        ['key' => 'parents', 'label' => 'Parents', 'href' => 'parents.php', 'icon' => 'bi-people'],
        ['key' => 'students', 'label' => 'Children', 'href' => 'students.php', 'icon' => 'bi-mortarboard'],
        ['key' => 'users', 'label' => 'Users', 'href' => 'users.php', 'icon' => 'bi-person-lines-fill'],
        ['section' => 'Insights'],
        // ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'bi-chat-dots'],
        ['key' => 'notifications', 'label' => 'Alerts', 'href' => 'notifications.php', 'icon' => 'bi-bell', 'count' => $counts['unreadAlerts'] ?? null],
        ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'bi-bar-chart'],
    ];

    foreach ($items as &$item) {
        if (isset($item['key'])) {
            $item['active'] = $item['key'] === $active;
        }
    }

    return $items;
}

function admin_nav_counts(): array
{
    try {
        $pdo = db();

        return [
            'activeSubscriptions' => (int) $pdo->query("SELECT COUNT(*) FROM monthly_plans WHERE status = 'active'")->fetchColumn(),
            'pendingRefunds' => (int) $pdo->query("SELECT COUNT(*) FROM monthly_plan_refunds WHERE status IN ('pending', 'approved')")->fetchColumn(),
            'pendingDrivers' => (int) $pdo->query("SELECT COUNT(*) FROM drivers WHERE approval_status = 'pending'")->fetchColumn(),
            'unreadAlerts' => (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn(),
        ];
    } catch (Throwable $exception) {
        return [];
    }
}

function admin_status_badge($status): string
{
    $value = strtolower(trim((string) $status));
    $tone = 'muted';

    if (in_array($value, ['approved', 'active', 'completed', 'paid', 'online', 'success'], true)) {
        $tone = 'success';
    } elseif (in_array($value, ['pending', 'assigned', 'driver_arriving', 'arrived', 'picked_up', 'in_transit'], true)) {
        $tone = 'warning';
    } elseif (in_array($value, ['cancelled', 'rejected', 'inactive', 'failed', 'danger'], true)) {
        $tone = 'danger';
    } elseif (in_array($value, ['open', 'available', 'unread'], true)) {
        $tone = 'info';
    }

    return '<span class="status-badge status-' . e($tone) . '">' . e(str_replace('_', ' ', $value ?: 'unknown')) . '</span>';
}

function render_sidebar($active)
{
    $items = admin_nav_items($active);
    ?>
    <div class="sidebar-brand">
        <img class="brand-logo" src="assets/trace-logo.png" alt="TRACE logo">
        <div>
            <div class="fw-bold">TRACE</div>
            <div class="brand-kicker">Operations Console</div>
        </div>
    </div>

    <nav class="nav flex-column sidebar-nav">
        <?php foreach ($items as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="nav-section-label"><?= e($item['section']) ?></div>
            <?php else: ?>
                <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span><?= e($item['label']) ?></span>
                    <?php if (!empty($item['count'])): ?><span class="nav-count"><?= e($item['count']) ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="small text-secondary mb-2">System health</div>
        <div class="d-flex align-items-center justify-content-between">
            <span class="badge bg-success-subtle text-success border border-success-subtle">Online</span>
            <span class="small text-secondary"><?= e(date('M d, Y')) ?></span>
        </div>
    </div>
    <?php
}

function admin_header($title, $active, $subtitle = '')
{
    $admin = require_admin();
    $flash = pull_flash();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | TRACE Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <link href="assets/admin.css" rel="stylesheet">
        <style>
            :root {
                --bs-primary: #078EFF;
                --bs-primary-rgb: 7, 142, 255;
                --bs-success: #22D61E;
                --bs-success-rgb: 34, 214, 30;
                --bs-warning: #FFC61B;
                --bs-warning-rgb: 255, 198, 27;
                --bs-dark: #2E3138;
                --bs-dark-rgb: 46, 49, 56;
            }
        </style>
    </head>
    <body>
        <aside class="admin-sidebar d-none d-lg-flex">
            <?php render_sidebar($active); ?>
        </aside>

        <div class="offcanvas offcanvas-start" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
            <div class="offcanvas-header border-bottom">
                <h5 class="offcanvas-title" id="adminSidebarLabel">TRACE Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-0 d-flex flex-column">
                <?php render_sidebar($active); ?>
            </div>
        </div>

        <div class="admin-shell">
            <header class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="h4 mb-0"><?= e($title) ?></h1>
                        <?php if ($subtitle !== ''): ?>
                            <div class="text-secondary small"><?= e($subtitle) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="topbar-actions">
                    <div class="admin-search d-none d-md-flex">
                        <i class="bi bi-search"></i>
                        <input type="search" data-admin-search placeholder="Filter visible records">
                    </div>
                    <div class="topbar-meta">
                        <div class="fw-semibold"><?= e(date('l')) ?></div>
                        <div><?= e(date('M d, Y h:i A')) ?></div>
                    </div>
                    <a class="btn btn-light position-relative" href="notifications.php" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e(full_name($admin)) ?: 'Admin' ?></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="reports.php">Reports</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <?php if ($flash): ?>
                    <div data-flash-type="<?= e($flash['type']) ?>" data-flash-message="<?= e($flash['message']) ?>"></div>
                <?php endif; ?>
    <?php
}

function admin_footer()
{
    ?>
            </main>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            const flash = document.querySelector('[data-flash-type]');
            if (flash) {
                Swal.fire({
                    icon: flash.dataset.flashType === 'error' ? 'error' : 'success',
                    title: flash.dataset.flashType === 'error' ? 'Action failed' : 'Success',
                    text: flash.dataset.flashMessage,
                    confirmButtonColor: '#078EFF'
                });
            }

            document.querySelectorAll('form[data-confirm]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: form.dataset.confirmTitle || 'Are you sure?',
                        text: form.dataset.confirm || 'This action cannot be undone.',
                        showCancelButton: true,
                        confirmButtonColor: '#078EFF',
                        cancelButtonColor: '#2E3138',
                        confirmButtonText: form.dataset.confirmButton || 'Yes, continue'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.removeAttribute('data-confirm');
                            form.submit();
                        }
                    });
                });
            });

            const adminSearch = document.querySelector('[data-admin-search]');
            if (adminSearch) {
                adminSearch.addEventListener('input', () => {
                    const query = adminSearch.value.trim().toLowerCase();
                    document.querySelectorAll('table tbody tr').forEach((row) => {
                        row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
                    });
                });
            }
        </script>
    </body>
    </html>
    <?php
}
