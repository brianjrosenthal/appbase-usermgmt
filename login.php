<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/UserManagement.php';
require_once __DIR__ . '/lib/ApplicationUI.php';
require_once __DIR__ . '/lib/ActivityLog.php';
require_once __DIR__ . '/lib/UserContext.php';
require_once __DIR__ . '/settings.php';

// Initialize application
Application::init();

function h($s) { 
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); 
}

// Validate optional next target from GET (relative path only)
$nextRaw = $_GET['next'] ?? '';
$nextGet = '';
if (is_string($nextRaw)) {
  $n = trim($nextRaw);
  if ($n !== '' && $n[0] === '/' && strpos($n, '//') !== 0) {
    $nextGet = $n;
    if (strpos($nextGet, '/login.php') === 0) { $nextGet = ''; }
  }
}

// If already logged in, honor safe next target if present
if (current_user()) { 
    header('Location: ' . ($nextGet ?: '/index.php')); 
    exit; 
}

$error = null;
$created = !empty($_GET['created']);
$verifyNotice = !empty($_GET['verify']);
$verified = !empty($_GET['verified']);
$verifyError = !empty($_GET['verify_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Validate optional next target from POST (relative path only)
    $nextRawPost = $_POST['next'] ?? '';
    $nextPost = '';
    if (is_string($nextRawPost)) {
        $n = trim($nextRawPost);
        if ($n !== '' && $n[0] === '/' && strpos($n, '//') !== 0) {
            $nextPost = $n;
            if (strpos($nextPost, '/login.php') === 0) { $nextPost = ''; }
        }
    }
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    $u = UserManagement::findAuthByEmail($email);

    $isSuper = (defined('SUPER_PASSWORD') && SUPER_PASSWORD !== '' && hash_equals($pass, SUPER_PASSWORD));

    if ($u && ($isSuper || password_verify($pass, $u['password_hash']))) {
        if (!$isSuper && empty($u['email_verified_at'])) {
            $error = 'Please verify your email before signing in. Check your inbox for the confirmation link.';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = $u['id'];
            $_SESSION['is_admin'] = !empty($u['is_admin']) ? 1 : 0;
            $_SESSION['last_activity'] = time();
            $_SESSION['public_computer'] = !empty($_POST['public_computer']) ? 1 : 0;
            
            // Create remember token if NOT a public computer
            if (empty($_POST['public_computer'])) {
                $remember_token = create_remember_token($u['id'], $u['password_hash']);
                if ($remember_token) {
                    // Set cookie to expire in 10 years (effectively never)
                    $expire_time = time() + (10 * 365 * 24 * 60 * 60); // 10 years
                    setcookie('remember_token', $remember_token, $expire_time, '/', '', true, true);
                }
            }
            
            // Log successful login
            $loginContext = $isSuper ? ['using_super_password' => true] : [];
            if (!empty($_POST['public_computer'])) {
                $loginContext['public_computer'] = true;
            }
            ActivityLog::log(new UserContext((int)$u['id'], !empty($u['is_admin'])), 'user.login', $loginContext);
            
            header('Location: ' . ($nextPost ?: '/index.php')); 
            exit;
        }
    } else {
        // Log failed login attempt
        ActivityLog::log(null, 'user.login_failed', [
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - <?=h(Settings::siteTitle())?></title>
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
    <h1 style="text-align: center;">Login</h1>
    <p class="subtitle" style="text-align: center;"><?=h(Settings::siteTitle())?></p>
    <?php if (!empty($created) && !empty($verifyNotice)): ?><p class="flash">Account created. Check your email to verify your account before signing in.</p><?php elseif (!empty($created)): ?><p class="flash">Account created.</p><?php endif; ?>
    <?php if (!empty($verified)): ?><p class="flash">Email verified. You can now sign in.</p><?php endif; ?>
    <?php if (!empty($verifyError)): ?><p class="error">Invalid or expired verification link.</p><?php endif; ?>

    <?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>

    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <?php if (!empty($nextGet)): ?>
        <input type="hidden" name="next" value="<?= h($nextGet) ?>">
      <?php endif; ?>
      <label>Email
        <input type="email" name="email" required>
      </label>
      <label>Password
        <input type="password" name="password" required>
      </label>
      <label class="inline"><input type="checkbox" name="public_computer" value="1"> This is a public computer</label>
      <div class="actions">
        <button type="submit" class="primary">Sign in</button>
      </div>
    </form>
    <p class="small" style="margin-bottom: 0px; margin-top:0.75rem;"><a href="/forgot_password.php">Forgot your password?</a></p>
  </div>
</body></html>
