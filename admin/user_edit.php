<?php
require_once __DIR__ . '/../partials.php';
require_once __DIR__ . '/../lib/UserManagement.php';
Application::init();
require_admin();

$msg = null;
$err = null;

// Get user ID
$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: /admin/users.php');
    exit;
}

// Load user data
$user = UserManagement::findById($userId);
if (!$user) {
    header('Location: /admin/users.php?err=' . urlencode('User not found.'));
    exit;
}

// Handle messages from evaluation script
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
if (isset($_GET['err'])) {
    $err = $_GET['err'];
}

// For repopulating form after errors - get from URL parameters or use current user data
$form = [];
$formFields = ['first_name', 'last_name', 'email', 'is_admin'];
foreach ($formFields as $field) {
    if (isset($_GET[$field])) {
        $form[$field] = $_GET[$field];
    } else {
        $form[$field] = $user[$field] ?? '';
    }
}

$me = current_user();
$canEditAdmin = ((int)$user['id'] !== (int)$me['id']); // Can't change own admin status

header_html('Edit User');
?>

<h2>Edit User</h2>
<?php if ($msg): ?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if ($err): ?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" action="/admin/user_edit_eval.php" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <input type="hidden" name="id" value="<?= (int)$userId ?>">
    
    <h3>User Information</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <label>First name
        <input type="text" name="first_name" value="<?=h($form['first_name'] ?? '')?>" required>
      </label>
      <label>Last name
        <input type="text" name="last_name" value="<?=h($form['last_name'] ?? '')?>" required>
      </label>
      <label>Email
        <input type="email" name="email" value="<?=h($form['email'] ?? '')?>" required>
      </label>
      <?php if ($canEditAdmin): ?>
        <label class="inline">
          <input type="checkbox" name="is_admin" value="1" <?= !empty($form['is_admin']) ? 'checked' : '' ?>> 
          Admin user
        </label>
      <?php else: ?>
        <div class="small" style="color: #6c757d;">
          Admin status: <?= !empty($user['is_admin']) ? 'Yes' : 'No' ?> (cannot change your own admin status)
        </div>
      <?php endif; ?>
    </div>

    <h3>Account Status</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
      <div>
        <strong>Email Verification:</strong>
        <?php if (!empty($user['email_verified_at'])): ?>
          <span class="status-verified">Verified</span> on <?= h(date('M j, Y g:i A', strtotime($user['email_verified_at']))) ?>
        <?php else: ?>
          <span class="status-pending">Pending verification</span>
        <?php endif; ?>
      </div>
      <div>
        <strong>Created:</strong> <?= h(date('M j, Y g:i A', strtotime($user['created_at']))) ?>
      </div>
    </div>

    <div class="actions">
      <button class="primary" type="submit">Update User</button>
      <a class="button" href="/admin/users.php">Back to Users</a>
      <?php if ($canEditAdmin): ?>
        <button type="button" class="button" onclick="if(confirm('Delete this user? This cannot be undone.')) { document.getElementById('deleteForm').submit(); }">Delete User</button>
      <?php endif; ?>
    </div>
  </form>
  
  <?php if ($canEditAdmin): ?>
    <form id="deleteForm" method="post" action="/admin/user_edit_eval.php" style="display: none;">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="id" value="<?= (int)$userId ?>">
      <input type="hidden" name="action" value="delete">
    </form>
  <?php endif; ?>
</div>

<?php footer_html(); ?>
