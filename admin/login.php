<?php
session_start();
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
                    <p class="text-secondary mb-0">Manage riders, students, bookings, and live trip operations.</p>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <form action="dashboard.php" method="get">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" value="admin@trace.test">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" value="password">
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="rememberAdmin" checked>
                                    <label class="form-check-label small text-secondary" for="rememberAdmin">Remember me</label>
                                </div>
                                <a class="small text-decoration-none" href="#">Forgot password?</a>
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

