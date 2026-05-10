<?php

require_once __DIR__ . '/includes/bootstrap.php';

admin_log('logout', 'users', isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null, 'Admin signed out.');
unset($_SESSION['admin_user_id']);
session_regenerate_id(true);
redirect_to('login.php');
