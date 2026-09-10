<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_login();

$user_id = current_user_id();
$errors = [];
$success = '';

// --- Handle: set/update monthly budget ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_monthly') {
    $amount = (float) ($_POST['monthly_amount'] ?? 0);
    if ($amount <= 0) {
        $errors[] = 'Monthly budget must be greater than zero.';
    } else {
        $firstOfMonth = date('Y-m-01');
        $stmt = $pdo->prepare("SELECT budget_id FROM budgets WHERE user_id = ? AND month = ?");
        $stmt->execute([$user_id, $firstOfMonth]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE budgets SET monthly_amount = ? WHERE budget_id = ?");
            $stmt->execute([$amount, $existing['budget_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO budgets (user_id, month, monthly_amount) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $firstOfMonth, $amount]);
        }
        $success = 'Monthly budget saved.';
    }
}

$budget = get_active_budget($pdo, $user_id);

// --- Handle: add a weekly budget ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_week' && $budget) {
    $week_number = (int) ($_POST['week_number'] ?? 0);
    $allocated   = (float) ($_POST['allocated_amount'] ?? 0);

    if ($week_number < 1 || $week_number > 5) {
        $errors[] = 'Week number must be between 1 and 5.';
    } elseif ($allocated <= 0) {
        $errors[] = 'Weekly amount must be greater than zero.';
    } else {
        $already_allocated = sum_allocated($pdo, $budget['budget_id']);
        if ($already_allocated + $allocated > (float) $budget['monthly_amount'] + 0.01) {
            $remaining = (float) $budget['monthly_amount'] - $already_allocated;
            $errors[] = "That would exceed your monthly budget. Only " . format_money(max(0,$remaining)) . " is unallocated.";
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO weekly_budgets (budget_id, week_number, allocated_amount)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE allocated_amount = VALUES(allocated_amount)"
            );
            $stmt->execute([$budget['budget_id'], $week_number, $allocated]);
            $success = "Week $week_number budget saved.";
        }
    }
}

// --- Handle: delete a weekly budget ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_week' && $budget) {
    $weekly_budget_id = (int) ($_POST['weekly_budget_id'] ?? 0);
    $stmt = $pdo->prepare(
        "DELETE wb FROM weekly_budgets wb JOIN budgets b ON wb.budget_id = b.budget_id
         WHERE wb.weekly_budget_id = ? AND b.user_id = ?"
    );
    $stmt->execute([$weekly_budget_id, $user_id]);
    $success = 'Week removed.';
}

$weekly_budgets = $budget ? get_weekly_budgets($pdo, $budget['budget_id']) : [];
$allocated_total = $budget ? sum_allocated($pdo, $budget['budget_id']) : 0;
$unallocated = $budget ? (float)$budget['monthly_amount'] - $allocated_total : 0;

$page_title = 'Budget';
$active = 'budget';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Budget</h1>
    <p>Set your monthly grocery budget, then split it across weeks.</p>
  </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card">
  <h2>Monthly budget — <?= date('F Y') ?></h2>
  <form method="post">
    <input type="hidden" name="action" value="set_monthly">
    <div class="form-row">
      <div>
        <label for="monthly_amount">Amount (₹)</label>
        <input type="number" step="0.01" min="0" id="monthly_amount" name="monthly_amount"
               value="<?= $budget ? htmlspecialchars($budget['monthly_amount']) : '' ?>" required>
      </div>
    </div>
    <button type="submit" class="btn"><?= $budget ? 'Update' : 'Set' ?> monthly budget</button>
  </form>
</div>

<?php if ($budget): ?>
<div class="card">
  <h2>Weekly split</h2>
  <p>Allocated so far: <?= format_money($allocated_total) ?> of <?= format_money((float)$budget['monthly_amount']) ?>
     &nbsp;·&nbsp; Unallocated: <strong><?= format_money(max(0,$unallocated)) ?></strong></p>

  <?php if (!empty($weekly_budgets)): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Week</th><th>Allocated</th><th>Spent</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($weekly_budgets as $wb): ?>
          <tr>
            <td>Week <?= (int)$wb['week_number'] ?></td>
            <td><?= format_money((float)$wb['allocated_amount']) ?></td>
            <td><?= format_money(get_weekly_spent($pdo, $wb['weekly_budget_id'])) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Remove this week?');">
                <input type="hidden" name="action" value="delete_week">
                <input type="hidden" name="weekly_budget_id" value="<?= (int)$wb['weekly_budget_id'] ?>">
                <button type="submit" class="btn small danger">Remove</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($unallocated > 0.01): ?>
  <form method="post">
    <input type="hidden" name="action" value="add_week">
    <div class="form-row">
      <div>
        <label for="week_number">Week number</label>
        <input type="number" min="1" max="5" id="week_number" name="week_number" required>
      </div>
      <div>
        <label for="allocated_amount">Amount for this week (₹)</label>
        <input type="number" step="0.01" min="0" max="<?= $unallocated ?>" id="allocated_amount" name="allocated_amount" required>
      </div>
    </div>
    <button type="submit" class="btn">Add / update week</button>
  </form>
  <?php else: ?>
    <p style="color:var(--color-text-soft);">Your full monthly budget is allocated across weeks.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
