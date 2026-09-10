<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$user_id = current_user_id();
$budget = get_active_budget($pdo, $user_id);
$weekly_budgets = $budget ? get_weekly_budgets($pdo, $budget['budget_id']) : [];

$selected_week_id = (int) ($_POST['weekly_budget_id'] ?? $_GET['week'] ?? 0);
if (!$selected_week_id && !empty($weekly_budgets)) {
    $selected_week_id = (int) $weekly_budgets[0]['weekly_budget_id'];
}

$success = '';
$result = ['items' => [], 'total' => 0.0];
$wb = null;

if ($selected_week_id) {
    $wb = get_weekly_budget_for_user($pdo, $selected_week_id, $user_id);
}

if ($wb) {
    $result = calculate_shopping_list($pdo, $selected_week_id, $user_id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_list') {
        save_shopping_list($pdo, $selected_week_id, $result);
        $success = 'Shopping list saved.';
    }
}

$allocated = $wb ? (float) $wb['allocated_amount'] : 0;
$over_budget = $result['total'] > $allocated;

$page_title = 'Shopping List';
$active = 'shopping';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Shopping List</h1>
    <p>Calculated from your meal plan, minus what's already in your pantry. Prices come from your Grocery Items catalog.</p>
  </div>
</div>

<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if (!$budget || empty($weekly_budgets)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">📅</div>
      <p>Set up a weekly budget first.</p>
      <a href="budget.php" class="btn">Set up your budget</a>
    </div>
  </div>
<?php else: ?>

<div class="card">
  <label for="week_select">Which week?</label>
  <form method="get" id="weekForm">
    <select id="week_select" name="week" onchange="document.getElementById('weekForm').submit()">
      <?php foreach ($weekly_budgets as $w): ?>
        <option value="<?= (int)$w['weekly_budget_id'] ?>" <?= $w['weekly_budget_id'] == $selected_week_id ? 'selected' : '' ?>>
          Week <?= (int)$w['week_number'] ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (empty($result['items'])): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">🛒</div>
      <p>Nothing to buy yet — plan some meals for this week, or your pantry already covers everything needed.</p>
      <a href="meal_planner.php?week=<?= (int)$selected_week_id ?>" class="btn">Go to Weekly Planner</a>
    </div>
  </div>
<?php else: ?>

<div class="card-grid">
  <div class="stat-card">
    <div class="stat-label">Weekly budget</div>
    <div class="stat-value"><?= format_money($allocated) ?></div>
  </div>
  <div class="stat-card <?= $over_budget ? '' : 'accent' ?>" style="<?= $over_budget ? 'background:var(--color-danger-bg);' : '' ?>">
    <div class="stat-label" style="<?= $over_budget ? 'color:var(--color-danger);' : '' ?>">Estimated cost</div>
    <div class="stat-value" style="<?= $over_budget ? 'color:var(--color-danger);' : '' ?>"><?= format_money($result['total']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label"><?= $over_budget ? 'Over budget by' : 'Remaining after shopping' ?></div>
    <div class="stat-value"><?= format_money(abs($allocated - $result['total'])) ?></div>
  </div>
</div>

<?php if ($over_budget): ?>
  <div class="alert error">
    This shopping list is <?= format_money($result['total'] - $allocated) ?> over your weekly budget.
    Consider swapping a meal for a cheaper one, or checking your pantry entries are up to date.
  </div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th>Item</th><th>Category</th><th>Needed</th><th>Have in pantry</th><th>To buy</th><th>Est. cost</th></tr></thead>
    <tbody>
      <?php foreach ($result['items'] as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td><span class="chip"><?= htmlspecialchars($item['category']) ?></span></td>
        <td><?= $item['required_qty'] ?> <?= $item['unit'] ?></td>
        <td><?= $item['pantry_qty'] ?> <?= $item['unit'] ?></td>
        <td><strong><?= $item['shopping_qty'] ?> <?= $item['unit'] ?></strong></td>
        <td><?= format_money($item['estimated_cost']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form method="post">
  <input type="hidden" name="action" value="save_list">
  <input type="hidden" name="weekly_budget_id" value="<?= (int)$selected_week_id ?>">
  <button type="submit" class="btn">Save this list</button>
</form>

<?php endif; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
