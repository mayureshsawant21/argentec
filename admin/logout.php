<?php
require_once __DIR__ . '/inc/bootstrap.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (user()) {
        log_activity('Signed out');
    }
    $_SESSION = [];
    session_regenerate_id(true);
    flash('info', 'You have been signed out.');
}
redirect('login.php');
