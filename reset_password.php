<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/UserManagement.php';
require_once __DIR__ . '/lib/ApplicationUI.php';
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
$error = null;
$success = false;

// Verify token is valid
$user = null;
if ($token) {
    $user = UserManagement::getUserByResetToken($token);
    if (!$user) {
        $error = 'Invalid or expired reset link.';
    }
} else {
    $error = 'Invalid reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    require_csrf();
    
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if ($password === '') {
        $error = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        if (UserManagement::completePasswordReset($token, $password)) {
            $success = true;
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password - <?=h(Settings::siteTitle())?></title>
<?=ApplicationUI::cssLink('/styles.css')?></head>
<body class="auth">
  <div class="card">
    <?php 
      $loginImageUrl = Settings::loginImageUrl();
      if ($loginImageUrl !== ''): 
    ?>
      <center>
        <img width="200" src="<?=h($loginImageUrl)?>" alt="Login Logo" class="logo" style="margin-bottom: 16px;">
      </center>
    <?php endif; ?>
    <h1>Reset Password</h1>
    <p class="subtitle"><?=h(Settings::siteTitle())?></p>
    
    <?php if ($success): ?>
      <p class="flash">Your password has been reset successfully.</p>
      <p><a href="/login.php">Sign In</a></p>
    <?php elseif ($user): ?>
      <?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>
      
      <p>Enter your new password for <strong><?=h($user['email'])?></strong>.</p>
      
      <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <label>New Password
          <input type="password" name="password" required minlength="8">
        </label>
        <label>Confirm New Password
          <input type="password" name="confirm_password" required minlength="8">
        </label>
        <div class="actions">
          <button type="submit" class="primary">Reset Password</button>
          <a href="/login.php" class="button">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <p class="error"><?=h($error)?></p>
      <p><a href="/forgot_password.php">Request a new reset link</a></p>
    <?php endif; ?>
  </div>
</body></html>
