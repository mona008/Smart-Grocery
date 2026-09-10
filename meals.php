<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$user_id = current_user_id();
$errors = [];
$success = '';
$meal_categories = ['Breakfast','Lunch','Dinner','Snack'];

// Add a new meal + its ingredients
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_meal') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';
    $servings = (int) ($_POST['servings'] ?? 1);
    $item_ids  = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $ing_units  = $_POST['ing_unit'] ?? [];

    if ($name === '' || !in_array($category, $meal_categories) || $servings < 1) {
        $errors[] = 'Please fill in the meal name, category, and servings.';
    }

    $ingredients = [];
    foreach ($item_ids as $i => $iid) {
        $iid = (int) $iid;
        $qty = (float) ($quantities[$i] ?? 0);
        $unit = $ing_units[$i] ?? '';
        if ($iid > 0 && $qty > 0 && in_array($unit, ['g','kg','ml','l','pcs'])) {
            $ingredients[] = [$iid, $qty, $unit];
        }
    }
    if (empty($ingredients)) {
        $errors[] = 'Add at least one ingredient with a quantity.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO meals (user_id, name, category, servings) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $name, $category, $servings]);
        $meal_id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO meal_ingredients (meal_id, item_id, quantity, unit) VALUES (?, ?, ?, ?)");
        foreach ($ingredients as [$iid, $qty, $unit]) {
            $stmt->execute([$meal_id, $iid, $qty, $unit]);
        }
        $success = "\"$name\" added with " . count($ingredients) . " ingredient(s).";
    }
}

// Delete a meal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_meal') {
    $meal_id = (int) ($_POST['meal_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM meals WHERE meal_id = ? AND user_id = ?");
    $stmt->execute([$meal_id, $user_id]);
    $success = 'Meal deleted.';
}

$stmt = $pdo->query("SELECT item_id, name, default_unit FROM grocery_items ORDER BY name");
$all_items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM meals WHERE user_id = ? ORDER BY category, name");
$stmt->execute([$user_id]);
$meals = $stmt->fetchAll();

// Fetch ingredients for all meals in one query, grouped by meal_id
$meal_ingredients = [];
if (!empty($meals)) {
    $meal_ids = array_column($meals, 'meal_id');
    $placeholders = implode(',', array_fill(0, count($meal_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT mi.meal_id, gi.name, mi.quantity, mi.unit
         FROM meal_ingredients mi JOIN grocery_items gi ON mi.item_id = gi.item_id
         WHERE mi.meal_id IN ($placeholders) ORDER BY gi.name"
    );
    $stmt->execute($meal_ids);
    foreach ($stmt->fetchAll() as $row) {
        $meal_ingredients[$row['meal_id']][] = $row;
    }
}

$page_title = 'Meals';
$active = 'meals';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Meals</h1>
    <p>Build reusable meals with ingredients — you'll assign these to days in the Weekly Planner.</p>
  </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
  <h2>Add a new meal</h2>
  <form method="post" id="mealForm">
    <input type="hidden" name="action" value="add_meal">
    <div class="form-row">
      <div>
        <label for="name">Meal name</label>
        <input type="text" id="name" name="name" required>
      </div>
      <div>
        <label for="category">Category</label>
        <select id="category" name="category" required>
          <?php foreach ($meal_categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="servings">Servings</label>
        <input type="number" min="1" id="servings" name="servings" value="1" required>
      </div>
    </div>

    <label>Ingredients</label>
    <div id="ingredientRows"></div>
    <button type="button" class="btn small secondary" id="addIngredientBtn">+ Add ingredient</button>

    <div><button type="submit" class="btn">Save meal</button></div>
  </form>
</div>

<div class="card">
  <h2>Your meals</h2>
  <?php if (empty($meals)): ?>
    <div class="empty-state">
      <div class="empty-icon">🍽️</div>
      <p>No meals yet — add your first one above.</p>
    </div>
  <?php else: ?>
    <?php foreach ($meals as $meal): ?>
      <div style="border-top:1px solid #F3E7D4; padding:0.9rem 0;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.4rem;">
          <div>
            <strong><?= htmlspecialchars($meal['name']) ?></strong>
            <span class="chip"><?= htmlspecialchars($meal['category']) ?></span>
            <small style="color:var(--color-text-soft);">Serves <?= (int)$meal['servings'] ?></small>
          </div>
          <form method="post" onsubmit="return confirm('Delete this meal?');">
            <input type="hidden" name="action" value="delete_meal">
            <input type="hidden" name="meal_id" value="<?= (int)$meal['meal_id'] ?>">
            <button type="submit" class="btn small danger">Delete</button>
          </form>
        </div>
        <p style="margin-top:0.4rem; color:var(--color-text-soft); font-size:0.9rem;">
          <?php
            $parts = [];
            foreach (($meal_ingredients[$meal['meal_id']] ?? []) as $ing) {
                $qty = rtrim(rtrim(number_format($ing['quantity'],2), '0'), '.');
                $parts[] = htmlspecialchars($ing['name']) . " ({$qty}{$ing['unit']})";
            }
            echo implode(', ', $parts) ?: 'No ingredients';
          ?>
        </p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
const allItems = <?= json_encode(array_map(fn($i) => ['id'=>$i['item_id'],'name'=>$i['name'],'unit'=>$i['default_unit']], $all_items)) ?>;

function itemOptions(selectedUnit) {
  return allItems.map(i => `<option value="${i.id}" data-unit="${i.unit}">${i.name}</option>`).join('');
}

function addIngredientRow() {
  const row = document.createElement('div');
  row.className = 'form-row';
  row.style.marginBottom = '0.5rem';
  row.innerHTML = `
    <div>
      <select name="item_id[]" required>
        <option value="">Choose ingredient…</option>
        ${itemOptions()}
      </select>
    </div>
    <div>
      <input type="number" step="0.01" min="0" name="quantity[]" placeholder="Quantity" required>
    </div>
    <div>
      <select name="ing_unit[]" required>
        <option value="g">g</option><option value="kg">kg</option>
        <option value="ml">ml</option><option value="l">l</option>
        <option value="pcs">pcs</option>
      </select>
    </div>
    <div>
      <button type="button" class="btn small danger" onclick="this.closest('.form-row').remove()">Remove</button>
    </div>
  `;
  document.getElementById('ingredientRows').appendChild(row);
}

document.getElementById('addIngredientBtn').addEventListener('click', addIngredientRow);
addIngredientRow(); // start with one row
</script>

<?php require_once 'includes/footer.php'; ?>
