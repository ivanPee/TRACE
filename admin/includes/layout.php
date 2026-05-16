<?php

require_once __DIR__ . '/bootstrap.php';

function admin_nav_items($active)
{
    $items = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2'],
        ['key' => 'users', 'label' => 'Users', 'href' => 'users.php', 'icon' => 'bi-person-lines-fill'],
        ['key' => 'riders', 'label' => 'Riders', 'href' => 'riders.php', 'icon' => 'bi-person-badge'],
        ['key' => 'parents', 'label' => 'Parents', 'href' => 'parents.php', 'icon' => 'bi-people'],
        ['key' => 'students', 'label' => 'Students', 'href' => 'students.php', 'icon' => 'bi-mortarboard'],
        ['key' => 'bookings', 'label' => 'Bookings', 'href' => 'bookings.php', 'icon' => 'bi-calendar-check'],
        ['key' => 'vehicles', 'label' => 'Vehicles', 'href' => 'vehicles.php', 'icon' => 'bi-truck'],
        // ['key' => 'messages', 'label' => 'Messages', 'href' => 'messages.php', 'icon' => 'bi-chat-dots'],
        ['key' => 'notifications', 'label' => 'Alerts', 'href' => 'notifications.php', 'icon' => 'bi-bell'],
        ['key' => 'reports', 'label' => 'Reports', 'href' => 'reports.php', 'icon' => 'bi-bar-chart'],
    ];

    foreach ($items as &$item) {
        $item['active'] = $item['key'] === $active;
    }

    return $items;
}

function render_sidebar($active)
{
    $items = admin_nav_items($active);
    ?>
    <div class="sidebar-brand">
        <div class="brand-mark">T</div>
        <div>
            <div class="fw-bold">TRACE</div>
            <small class="text-secondary">Admin Panel</small>
        </div>
    </div>

    <nav class="nav flex-column sidebar-nav">
        <?php foreach ($items as $item): ?>
            <a class="nav-link <?= $item['active'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i>
                <span><?= e($item['label']) ?></span>
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
                    <a class="btn btn-light position-relative" href="notifications.php" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e(full_name($admin)) ?: 'Admin' ?></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="reports.php">Activity logs</a></li>
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
                    confirmButtonColor: '#0d6efd'
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
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: form.dataset.confirmButton || 'Yes, continue'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.removeAttribute('data-confirm');
                            form.submit();
                        }
                    });
                });
            });
        </script>
    </body>
    </html>
    <?php
}
