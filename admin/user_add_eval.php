<?php
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/UserManagement.php';
Application::init();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/users.php');
    exit;
}

require_csrf();

// Get form data
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$is_admin = !empty($_POST['is_admin']) ? 1 : 0;

// Validation
$errors = [];
if ($first_name === '') {
    $errors[] = 'First name is required.';
}
if ($last_name === '') {
    $errors[] = 'Last name is required.';
}
if ($email === '') {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email is invalid.';
}

// Check if email already exists
if (empty($errors) && UserManagement::emailExists($email)) {
    $errors[] = 'Email already exists.';
}

if (!empty($errors)) {
    // Redirect back to form with errors and form data
    $params = [
        'err' => implode(' ', $errors),
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'is_admin' => $is_admin
    ];
    $query = http_build_query($params);
    header('Location: /admin/user_add.php?' . $query);
    exit;
}

try {
    // Create user with password setup flow
    $data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'is_admin' => $is_admin,
        'require_password_setup' => true
    ];
    
    $ctx = UserContext::getLoggedInUserContext();
    $userId = UserManagement::createUser($ctx, $data);
    
    // Success - redirect to edit page for the new user
    header('Location: /admin/user_edit.php?id=' . $userId . '&msg=' . urlencode('User created successfully.'));
    exit;
    
} catch (Exception $e) {
    // Error creating user - redirect back to form
    $params = [
        'err' => 'Error creating user: ' . $e->getMessage(),
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'is_admin' => $is_admin
    ];
    $query = http_build_query($params);
    header('Location: /admin/user_add.php?' . $query);
    exit;
}
