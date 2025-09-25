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

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    
    if ($email === '') {
        $error = 'Email is required.';
    } else {
        // Always show success message for security (don't reveal if email exists)
        $token = UserManagement::setPasswordResetToken($email);
        $success = true;
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password - <?=h(Settings::siteTitle())?></title>
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
    <h1>Forgot Password</h1>
    <p class="subtitle"><?=h(Settings::siteTitle())?></p>
    
    <?php if ($success): ?>
      <p class="flash">If an account with that email exists, we've sent you a password reset link.</p>
      <p><a href="/login.php">Back to Login</a></p>
    <?php else: ?>
      <?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>
      
      <p>Enter your email address and we'll send you a link to reset your password.</p>
      
      <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
        <label>Email
          <input type="email" name="email" required>
        </label>
        <div class="actions">
          <button type="submit" class="primary">Send Reset Link</button>
          <a href="/login.php" class="button">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</body></html>
