<?php

require_once __DIR__ . '/includes/bootstrap.php';

if (current_admin()) {
    redirect_to('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $stmt = db()->prepare(
        'SELECT users.*, roles.code AS role_code
         FROM users
         JOIN roles ON roles.id = users.role_id
         WHERE users.email = ? AND roles.code = "admin"
         LIMIT 1'
    );
    $stmt->execute([post_value('email')]);
    $admin = $stmt->fetch();

    if ($admin && password_verify((string) post_value('password'), $admin['password_hash']) && $admin['status'] === 'active') {
        $_SESSION['admin_user_id'] = (int) $admin['id'];
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $admin['id']]);
        admin_log('login', 'users', (int) $admin['id'], 'Admin signed in.');
        redirect_to('dashboard.php');
    }

    flash('error', 'Invalid admin email or password.');
    redirect_to('login.php');
}

$flash = pull_flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TRACE Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/admin.css" rel="stylesheet">
</head>
<body class="login-page">
    <main class="container min-vh-100 d-flex align-items-center py-5">
        <div class="row justify-content-center w-100">
            <div class="col-md-7 col-lg-5 col-xl-4">
                <div class="text-center mb-4">
                    <div class="brand-mark mx-auto mb-3">T</div>
                    <h1 class="h3 mb-1">TRACE Admin</h1>
                    <p class="text-secondary mb-0">Secure operations for users, trips, messages, and reports.</p>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-danger"><?= e($flash['message']) ?></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <form method="post">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                    <input name="email" type="email" class="form-control" required autocomplete="email">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                                    <input name="password" type="password" class="form-control" required autocomplete="current-password">
                                </div>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
