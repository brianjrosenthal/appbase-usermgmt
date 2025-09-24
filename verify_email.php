<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/UserManagement.php';
require_once __DIR__ . '/settings.php';

// Initialize application
Application::init();

function h($s) { 
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); 
}

// If already logged in, redirect to home
if (current_user()) {
    header('Location: /index.php');
    exit;
}

$token = $_GET['token'] ?? '';
$success = false;
$error = null;

if ($token) {
    if (UserManagement::verifyByToken($token)) {
        $success = true;
    } else {
        $error = 'Invalid or expired verification link.';
    }
} else {
    $error = 'Invalid verification link.';
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify Email - <?=h(Settings::siteTitle())?></title><link rel="stylesheet" href="/styles.css"></head>
<body class="auth">
  <div class="card">
    <h1>Email Verification</h1>
    <p class="subtitle"><?=h(Settings::siteTitle())?></p>
    
    <?php if ($success): ?>
      <p class="flash">Your email has been verified successfully!</p>
      <p><a href="/login.php?verified=1">Sign In</a></p>
    <?php else: ?>
      <p class="error"><?=h($error)?></p>
      <p><a href="/login.php?verify_error=1">Back to Login</a></p>
    <?php endif; ?>
  </div>
</body></html>
