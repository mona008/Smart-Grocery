<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$errors = [];
$success = '';
$categories = ['Fruits','Vegetables','Grains','Dairy','Protein','Spices','Other'];
$units = ['g','kg','ml','l','pcs'];

// Add new grocery item + starting price
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $unit = $_POST['unit'] ?? '';
    $price = (float) ($_POST['price'] ?? 0);

    if ($name === '' || !in_array($category, $categories) || !in_array($unit, $units) || $price <= 0) {
        $errors[] = 'Please fill in all fields correctly.';
    } else {
        $stmt = $pdo->prepare("SELECT item_id FROM grocery_items WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $errors[] = "\"$name\" already exists in the catalog.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO grocery_items (name, category, default_unit) VALUES (?, ?, ?)");
            $stmt->execute([$name, $category, $unit]);
            $item_id = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO grocery_prices (item_id, price_per_unit) VALUES (?, ?)");
            $stmt->execute([$item_id, $price]);
            $success = "\"$name\" added to the catalog.";
        }
    }
}

// Update a price
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_price') {
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    if ($price > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO grocery_prices (item_id, price_per_unit) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE price_per_unit = VALUES(price_per_unit)"
        );
        $stmt->execute([$item_id, $price]);
        $success = 'Price updated.';
    }
}

$stmt = $pdo->query(
    "SELECT gi.item_id, gi.name, gi.category, gi.default_unit, gp.price_per_unit
     FROM grocery_items gi
     LEFT JOIN grocery_prices gp ON gi.item_id = gp.item_id
     ORDER BY gi.category, gi.name"
);
$items = $stmt->fetchAll();

$page_title = 'Grocery Items';
$active = 'grocery';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Grocery Items</h1>
    <p>The catalog of items and prices used for all cost calculations.</p>
  </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
  <h2>Add a new item</h2>
  <form method="post">
    <input type="hidden" name="action" value="add_item">
    <div class="form-row">
      <div>
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required>
      </div>
      <div>
        <label for="category">Category</label>
        <select id="category" name="category" required>
          <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="unit">Unit</label>
        <select id="unit" name="unit" required>
          <?php foreach ($units as $u): ?><option value="<?= $u ?>"><?= $u ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="price">Price per unit (₹)</label>
        <input type="number" step="0.01" min="0" id="price" name="price" required>
      </div>
    </div>
    <button type="submit" class="btn">Add item</button>
  </form>
</div>
<div class="catalog-toolbar">
    <div class="catalog-search">
        <label for="grocerySearch">🔍 Search groceries</label>
        <input
            type="text"
            id="grocerySearch"
            placeholder="Search by item name or category..."
            autocomplete="off">
    </div>

    <div class="catalog-filter">
        <label for="categoryFilter">Category</label>
        <select id="categoryFilter">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars(strtolower($c)) ?>">
                    <?= htmlspecialchars($c) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<p class="catalog-result">
    Showing <strong id="groceryResultCount"><?= count($items) ?></strong>
    grocery items
</p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Item</th><th>Category</th><th>Unit</th><th>Price</th><th>Update price</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr
          class="grocery-row"
          data-name="<?= htmlspecialchars(strtolower($item['name'])) ?>"
          data-category="<?= htmlspecialchars(strtolower($item['category'])) ?>"
      >
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td><span class="chip"><?= htmlspecialchars($item['category']) ?></span></td>
        <td><?= htmlspecialchars($item['default_unit']) ?></td>
        <td><?= $item['price_per_unit'] !== null ? format_money((float)$item['price_per_unit']) : '—' ?></td>
        <td>
          <form method="post" style="display:flex; gap:0.4rem; align-items:center;">
            <input type="hidden" name="action" value="update_price">
            <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
            <input type="number" step="0.01" min="0" name="price" placeholder="New price" style="width:110px;">
            <button type="submit" class="btn small secondary">Save</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once 'includes/footer.php'; ?>
