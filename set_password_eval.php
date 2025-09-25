<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/UserManagement.php';
require_once __DIR__ . '/lib/ActivityLog.php';
require_once __DIR__ . '/lib/UserContext.php';

// Initialize application
Application::init();

// Must be POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

require_csrf();

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate inputs
if ($token === '') {
    header('Location: /login.php?error=invalid_token');
    exit;
}

if ($password === '' || $confirmPassword === '') {
    header('Location: /set_password.php?token=' . urlencode($token) . '&error=missing_fields');
    exit;
}

if (strlen($password) < 8) {
    header('Location: /set_password.php?token=' . urlencode($token) . '&error=password_too_short');
    exit;
}

if ($password !== $confirmPassword) {
    header('Location: /set_password.php?token=' . urlencode($token) . '&error=passwords_dont_match');
    exit;
}

// Find user by token with empty password
$st = pdo()->prepare('SELECT * FROM users WHERE email_verify_token = ? AND password_hash = "" LIMIT 1');
$st->execute([$token]);
$user = $st->fetch();

if (!$user) {
    header('Location: /login.php?error=invalid_token');
    exit;
}

try {
    // Set the password and clear the verification token
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $updateSt = pdo()->prepare(
        'UPDATE users SET password_hash = ?, email_verify_token = NULL, email_verified_at = NOW() WHERE id = ?'
    );
    $success = $updateSt->execute([$passwordHash, (int)$user['id']]);
    
    if (!$success) {
        header('Location: /set_password.php?token=' . urlencode($token) . '&error=database_error');
        exit;
    }
    
    // Log the initial password setup
    ActivityLog::log(new UserContext((int)$user['id'], !empty($user['is_admin'])), 'user.initial_password_set', []);
    
    // Log the user in automatically
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['is_admin'] = !empty($user['is_admin']) ? 1 : 0;
    $_SESSION['last_activity'] = time();
    $_SESSION['public_computer'] = 0; // Not a public computer for password setup
    
    // Log the automatic login
    ActivityLog::log(new UserContext((int)$user['id'], !empty($user['is_admin'])), 'user.login', [
        'automatic_after_password_setup' => true
    ]);
    
    // Redirect to homepage
    header('Location: /index.php?password_set=1');
    exit;
    
} catch (Throwable $e) {
    error_log('Password setup error: ' . $e->getMessage());
    header('Location: /set_password.php?token=' . urlencode($token) . '&error=system_error');
    exit;
}
