<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$user_id = current_user_id();
$errors = [];
$success = '';

$budget = get_active_budget($pdo, $user_id);
$weekly_budgets = $budget ? get_weekly_budgets($pdo, $budget['budget_id']) : [];

$selected_week_id = (int) ($_POST['weekly_budget_id'] ?? $_GET['week'] ?? 0);
if (!$selected_week_id && !empty($weekly_budgets)) {
    $selected_week_id = (int) $weekly_budgets[0]['weekly_budget_id'];
}

$slots = ['Breakfast','Lunch','Dinner','Snack'];

// Save the plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_plan') {
    $wb = get_weekly_budget_for_user($pdo, $selected_week_id, $user_id);
    if (!$wb) {
        $errors[] = 'Invalid week selected.';
    } else {
        $pdo->prepare("DELETE FROM meal_plans WHERE weekly_budget_id = ?")->execute([$selected_week_id]);
        $stmt = $pdo->prepare(
            "INSERT INTO meal_plans (weekly_budget_id, day_of_week, meal_type, meal_id) VALUES (?, ?, ?, ?)"
        );
        $plan = $_POST['plan'] ?? [];
        $count = 0;
        for ($day = 1; $day <= 7; $day++) {
            foreach ($slots as $slot) {
                $meal_id = (int) ($plan[$day][$slot] ?? 0);
                if ($meal_id > 0) {
                    $stmt->execute([$selected_week_id, $day, $slot, $meal_id]);
                    $count++;
                }
            }
        }
        $success = "Plan saved with $count meal(s).";
    }
}

// Load current plan for selected week
$current_plan = [];
if ($selected_week_id) {
    $stmt = $pdo->prepare("SELECT day_of_week, meal_type, meal_id FROM meal_plans WHERE weekly_budget_id = ?");
    $stmt->execute([$selected_week_id]);
    foreach ($stmt->fetchAll() as $row) {
        $current_plan[$row['day_of_week']][$row['meal_type']] = $row['meal_id'];
    }
}

$stmt = $pdo->prepare("SELECT meal_id, name, category FROM meals WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$meals = $stmt->fetchAll();

$page_title = 'Weekly Meal Planner';
$active = 'planner';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Weekly Meal Planner</h1>
    <p>Assign meals to each day. The Shopping List page will calculate what you need from this plan.</p>
  </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if (!$budget || empty($weekly_budgets)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">📅</div>
      <p>You need at least one weekly budget before planning meals.</p>
      <a href="budget.php" class="btn">Set up your budget</a>
    </div>
  </div>
<?php elseif (empty($meals)): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">🍽️</div>
      <p>You haven't added any meals yet.</p>
      <a href="meals.php" class="btn">Add your first meal</a>
    </div>
  </div>
<?php else: ?>

<div class="card">
  <label for="week_select">Which week are you planning?</label>
  <form method="get" id="weekForm">
    <select id="week_select" name="week" onchange="document.getElementById('weekForm').submit()">
      <?php foreach ($weekly_budgets as $wb): ?>
        <option value="<?= (int)$wb['weekly_budget_id'] ?>" <?= $wb['weekly_budget_id'] == $selected_week_id ? 'selected' : '' ?>>
          Week <?= (int)$wb['week_number'] ?> (<?= format_money((float)$wb['allocated_amount']) ?> budget)
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<form method="post">
  <input type="hidden" name="action" value="save_plan">
  <input type="hidden" name="weekly_budget_id" value="<?= (int)$selected_week_id ?>">

  <div class="table-wrap">
    <div class="planner-grid" style="padding:0.75rem;">
      <div class="planner-head"></div>
      <?php for ($day = 1; $day <= 7; $day++): ?>
        <div class="planner-head"><?= day_name($day) ?></div>
      <?php endfor; ?>

      <?php foreach ($slots as $slot): ?>
        <div class="planner-slot-label"><?= $slot ?></div>
        <?php for ($day = 1; $day <= 7; $day++):
          $current = $current_plan[$day][$slot] ?? 0;
        ?>
          <div class="planner-cell">
            <select name="plan[<?= $day ?>][<?= $slot ?>]">
              <option value="0">—</option>
              <?php foreach ($meals as $meal): ?>
                <option value="<?= (int)$meal['meal_id'] ?>" <?= $current == $meal['meal_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($meal['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endfor; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <button type="submit" class="btn" style="margin-top:1rem;">Save plan</button>
  <a href="shopping_list.php?week=<?= (int)$selected_week_id ?>" class="btn secondary" style="margin-top:1rem;">Generate shopping list →</a>
</form>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
