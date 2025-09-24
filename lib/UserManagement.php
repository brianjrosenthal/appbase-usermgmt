<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/../mailer.php';

class UserManagement {
    private static function pdo(): PDO {
        return pdo();
    }

    private static function str(string $v): string {
        return trim($v);
    }

    private static function normEmail(?string $email): ?string {
        if ($email === null) return null;
        $email = strtolower(trim($email));
        return $email === '' ? null : $email;
    }

    private static function boolInt($v): int {
        return !empty($v) ? 1 : 0;
    }

    // Find user by email for authentication
    public static function findAuthByEmail(string $email): ?array {
        $email = self::normEmail($email);
        if (!$email) return null;
        $st = self::pdo()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    // Find user by ID
    public static function findById(int $id): ?array {
        $st = self::pdo()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    // Create a new user (admin-created, auto-verified)
    public static function createUser(array $data): int {
        $first = self::str($data['first_name'] ?? '');
        $last = self::str($data['last_name'] ?? '');
        $email = self::normEmail($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $isAdmin = self::boolInt($data['is_admin'] ?? 0);

        if ($first === '' || $last === '' || !$email || $password === '') {
            throw new InvalidArgumentException('Missing required fields for user creation.');
        }

        // Check if email already exists
        if (self::emailExists($email)) {
            throw new InvalidArgumentException('Email already exists.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $st = self::pdo()->prepare(
            "INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verify_token,email_verified_at)
             VALUES (?,?,?,?,?,NULL,NOW())"
        );
        $st->execute([$first, $last, $email, $hash, $isAdmin]);
        return (int)self::pdo()->lastInsertId();
    }

    // Create user with email verification required
    public static function createUserWithVerification(array $data): int {
        $first = self::str($data['first_name'] ?? '');
        $last = self::str($data['last_name'] ?? '');
        $email = self::normEmail($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($first === '' || $last === '' || !$email || $password === '') {
            throw new InvalidArgumentException('Missing required fields for user creation.');
        }

        // Check if email already exists
        if (self::emailExists($email)) {
            throw new InvalidArgumentException('Email already exists.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $st = self::pdo()->prepare(
            "INSERT INTO users (first_name,last_name,email,password_hash,is_admin,email_verify_token,email_verified_at)
             VALUES (?,?,?,?,0,?,NULL)"
        );
        $st->execute([$first, $last, $email, $hash, $token]);
        $id = (int)self::pdo()->lastInsertId();

        // Send verification email
        send_verification_email($email, $token, $first);

        return $id;
    }

    // Verify email by token
    public static function verifyByToken(string $token): bool {
        if ($token === '') return false;
        $pdo = self::pdo();
        $st = $pdo->prepare('SELECT id FROM users WHERE email_verify_token = ? LIMIT 1');
        $st->execute([$token]);
        $row = $st->fetch();
        if (!$row) return false;

        $upd = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL WHERE id = ?');
        return $upd->execute([(int)$row['id']]);
    }

    // Set password reset token
    public static function setPasswordResetToken(string $email): ?string {
        $email = self::normEmail($email);
        if (!$email) return null;

        $user = self::findAuthByEmail($email);
        if (!$user) return null;

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (30 * 60)); // 30 minutes

        $st = self::pdo()->prepare(
            "UPDATE users SET password_reset_token_hash=?, password_reset_expires_at=? WHERE id=?"
        );
        $st->execute([$tokenHash, $expiresAt, (int)$user['id']]);

        // Send reset email
        send_password_reset_email($email, $token, $user['first_name'] ?? '');

        return $token;
    }

    // Verify password reset token and get user
    public static function getUserByResetToken(string $token): ?array {
        if ($token === '') return null;
        
        $tokenHash = hash('sha256', $token);
        $st = self::pdo()->prepare(
            'SELECT * FROM users WHERE password_reset_token_hash = ? AND password_reset_expires_at > NOW() LIMIT 1'
        );
        $st->execute([$tokenHash]);
        $row = $st->fetch();
        return $row ?: null;
    }

    // Complete password reset
    public static function completePasswordReset(string $token, string $newPassword): bool {
        $user = self::getUserByResetToken($token);
        if (!$user) return false;

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $st = self::pdo()->prepare(
            'UPDATE users SET password_hash=?, password_reset_token_hash=NULL, password_reset_expires_at=NULL WHERE id=?'
        );
        return $st->execute([$hash, (int)$user['id']]);
    }

    // Change password
    public static function changePassword(int $id, string $newPassword): bool {
        if ($newPassword === '') {
            throw new InvalidArgumentException('New password is required.');
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $st = self::pdo()->prepare("UPDATE users SET password_hash=? WHERE id=?");
        return $st->execute([$hash, $id]);
    }

    // Check if email exists
    public static function emailExists(string $email): bool {
        $norm = self::normEmail($email);
        if (!$norm) return false;
        $st = self::pdo()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $st->execute([$norm]);
        return (bool)$st->fetchColumn();
    }

    // List all users (admin only)
    public static function listUsers(string $search = ''): array {
        $sql = 'SELECT id, first_name, last_name, email, is_admin, email_verified_at, created_at FROM users';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?';
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= ' ORDER BY last_name, first_name';

        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    // Update user profile
    public static function updateProfile(int $id, array $fields): bool {
        $allowed = ['first_name', 'last_name', 'email'];
        $set = [];
        $params = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) continue;

            if ($key === 'email') {
                $val = self::normEmail($fields['email']);
                $set[] = 'email = ?';
                $params[] = $val;
            } else {
                $val = self::str($fields[$key]);
                if ($val === '') {
                    throw new InvalidArgumentException("$key cannot be empty");
                }
                $set[] = "$key = ?";
                $params[] = $val;
            }
        }

        if (empty($set)) return false;
        $params[] = $id;

        $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?';
        $st = self::pdo()->prepare($sql);
        return $st->execute($params);
    }

    // Delete user
    public static function deleteUser(int $id): bool {
        $st = self::pdo()->prepare('DELETE FROM users WHERE id = ?');
        return $st->execute([$id]);
    }

    // Set admin flag
    public static function setAdminFlag(int $id, bool $isAdmin): bool {
        $st = self::pdo()->prepare('UPDATE users SET is_admin = ? WHERE id = ?');
        return $st->execute([$isAdmin ? 1 : 0, $id]);
    }
}
