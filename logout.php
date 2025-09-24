<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';

// Initialize application
Application::init();

// Clear session
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Redirect to login
header('Location: /login.php');
exit;
