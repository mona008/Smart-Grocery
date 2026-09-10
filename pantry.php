<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$user_id = current_user_id();
$errors = [];
$success = '';

// Add or update pantry item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pantry') {
    $item_id  = (int) ($_POST['item_id'] ?? 0);
    $quantity = (float) ($_POST['quantity'] ?? 0);
    $unit     = $_POST['unit'] ?? '';

    if ($item_id <= 0 || $quantity < 0 || !in_array($unit, ['g','kg','ml','l','pcs'])) {
        $errors[] = 'Please choose an item and a valid quantity.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO pantry (user_id, item_id, quantity, unit) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), unit = VALUES(unit)"
        );
        $stmt->execute([$user_id, $item_id, $quantity, $unit]);
        $success = 'Pantry updated.';
    }
}

// Remove pantry item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_pantry') {
    $pantry_id = (int) ($_POST['pantry_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM pantry WHERE pantry_id = ? AND user_id = ?");
    $stmt->execute([$pantry_id, $user_id]);
    $success = 'Item removed from pantry.';
}

$stmt = $pdo->query("SELECT item_id, name, category, default_unit FROM grocery_items ORDER BY category, name");
$all_items = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT p.pantry_id, p.quantity, p.unit, gi.item_id, gi.name, gi.category
     FROM pantry p JOIN grocery_items gi ON p.item_id = gi.item_id
     WHERE p.user_id = ? ORDER BY gi.category, gi.name"
);
$stmt->execute([$user_id]);
$pantry_items = $stmt->fetchAll();

$page_title = 'Pantry';
$active = 'pantry';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Pantry</h1>
    <p>What you already have at home — the calculation engine subtracts this from your shopping list.</p>
  </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
  <h2>Add / update a pantry item</h2>
  <form method="post">
    <input type="hidden" name="action" value="save_pantry">
    <div class="form-row">
      <div>
        <label for="item_id">Item</label>
        <select id="item_id" name="item_id" required>
          <option value="">Choose an item…</option>
          <?php foreach ($all_items as $item): ?>
            <option value="<?= (int)$item['item_id'] ?>"><?= htmlspecialchars($item['name']) ?> (<?= $item['category'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="quantity">Quantity</label>
        <input type="number" step="0.01" min="0" id="quantity" name="quantity" required>
      </div>
      <div>
        <label for="unit">Unit</label>
        <select id="unit" name="unit" required>
          <option value="g">g</option><option value="kg">kg</option>
          <option value="ml">ml</option><option value="l">l</option>
          <option value="pcs">pcs</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn">Save to pantry</button>
  </form>
</div>

<div class="card">
  <h2>Your pantry</h2>
  <?php if (empty($pantry_items)): ?>
    <div class="empty-state">
      <div class="empty-icon">🧺</div>
      <p>Your pantry is empty — add what you already have at home.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Category</th><th>Quantity</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pantry_items as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><span class="chip"><?= htmlspecialchars($p['category']) ?></span></td>
            <td><?= rtrim(rtrim(number_format($p['quantity'],2), '0'), '.') ?> <?= htmlspecialchars($p['unit']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Remove from pantry?');">
                <input type="hidden" name="action" value="remove_pantry">
                <input type="hidden" name="pantry_id" value="<?= (int)$p['pantry_id'] ?>">
                <button type="submit" class="btn small danger">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
