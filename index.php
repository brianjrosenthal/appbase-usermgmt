<?php
require_once __DIR__ . '/partials.php';
Application::init();
require_login();

$me = current_user();
$announcement = Settings::announcement();
$siteTitle = Settings::siteTitle();

header_html('Home');
?>

<?php if (trim($announcement) !== ''): ?>
  <p class="announcement"><?=h($announcement)?></p>
<?php endif; ?>

<div class="card">
  <h2>Welcome to <?= h($siteTitle) ?></h2>
  <p>Hello, <?= h($me['first_name'] ?? '') ?>!</p>
  <p>This is your RAG (Retrieval Augmented Generation) knowledge base application. Here you'll be able to upload documents and build a custom GPT that responds with answers from those documents.</p>
  <p class="small">The document upload and RAG functionality will be implemented in future phases.</p>
</div>

<?php footer_html(); ?>
