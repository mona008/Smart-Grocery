<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_login();

$user_id = current_user_id();
$errors = [];
$success = '';

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->execute([$hash, $user_id]);
        $success = 'Password updated.';
    }
}

$page_title = 'Profile';
$active = 'profile';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div><h1>Profile</h1></div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
  <h2>Account details</h2>
  <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
  <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
  <p><strong>Member since:</strong> <?= date('d M Y', strtotime($user['created_at'])) ?></p>
</div>

<div class="card">
  <h2>Change password</h2>
  <form method="post">
    <input type="hidden" name="action" value="update_password">
    <label for="current_password">Current password</label>
    <input type="password" id="current_password" name="current_password" required>
    <label for="new_password">New password</label>
    <input type="password" id="new_password" name="new_password" required>
    <label for="confirm_password">Confirm new password</label>
    <input type="password" id="confirm_password" name="confirm_password" required>
    <button type="submit" class="btn">Update password</button>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>
