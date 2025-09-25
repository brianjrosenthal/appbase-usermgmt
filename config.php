<?php
// Main configuration for RAG application
require_once __DIR__ . '/config.local.php';
require_once __DIR__ . '/lib/UserContext.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bootstrap UserContext from session
UserContext::bootstrapFromSession();

// Database connection
function pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// CSRF token functions
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
}

// Remember me token functions
function create_remember_token(int $userId, string $passwordHash): ?string {
    if (!defined('REMEMBER_TOKEN_KEY') || REMEMBER_TOKEN_KEY === '') {
        return null;
    }
    
    $payload = $userId . ':' . substr($passwordHash, 0, 20);
    $signature = hash_hmac('sha256', $payload, REMEMBER_TOKEN_KEY);
    return base64_encode($payload . ':' . $signature);
}

function verify_remember_token(string $token): ?int {
    if (!defined('REMEMBER_TOKEN_KEY') || REMEMBER_TOKEN_KEY === '') {
        return null;
    }
    
    $decoded = base64_decode($token, true);
    if ($decoded === false) return null;
    
    $parts = explode(':', $decoded);
    if (count($parts) !== 3) return null;
    
    [$userId, $hashPrefix, $signature] = $parts;
    $payload = $userId . ':' . $hashPrefix;
    $expectedSignature = hash_hmac('sha256', $payload, REMEMBER_TOKEN_KEY);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }
    
    // Verify user exists and hash prefix matches
    $st = pdo()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$userId]);
    $row = $st->fetch();
    
    if (!$row || substr($row['password_hash'], 0, 20) !== $hashPrefix) {
        return null;
    }
    
    return (int)$userId;
}

// Current user functions
function current_user(): ?array {
    if (!empty($_SESSION['uid'])) {
        static $user = null;
        if ($user === null) {
            $st = pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $st->execute([(int)$_SESSION['uid']]);
            $user = $st->fetch() ?: false;
        }
        return $user ?: null;
    }
    
    // Check remember me token
    if (!empty($_COOKIE['remember_token'])) {
        $userId = verify_remember_token($_COOKIE['remember_token']);
        if ($userId) {
            $st = pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $user = $st->fetch();
            if ($user && !empty($user['email_verified_at'])) {
                // Auto-login from remember token
                session_regenerate_id(true);
                $_SESSION['uid'] = $user['id'];
                $_SESSION['is_admin'] = !empty($user['is_admin']) ? 1 : 0;
                $_SESSION['last_activity'] = time();
                return $user;
            }
        }
        // Invalid token, clear it
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    return null;
}

function require_login(): void {
    if (!current_user()) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /login.php' . ($next ? '?next=' . urlencode($next) : ''));
        exit;
    }
}

function require_admin(): void {
    $user = current_user();
    if (!$user || empty($user['is_admin'])) {
        http_response_code(403);
        die('Admin access required');
    }
}
