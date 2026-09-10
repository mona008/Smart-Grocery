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

// Log an expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    $weekly_budget_id = (int) ($_POST['weekly_budget_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $spent_at = $_POST['spent_at'] ?? date('Y-m-d');

    $wb = get_weekly_budget_for_user($pdo, $weekly_budget_id, $user_id);
    if (!$wb) {
        $errors[] = 'Please choose a valid week.';
    } elseif ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO expenses (weekly_budget_id, amount, description, spent_at) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$weekly_budget_id, $amount, $description, $spent_at]);
        $success = 'Expense logged.';
    }
}

// Delete an expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    $expense_id = (int) ($_POST['expense_id'] ?? 0);
    $stmt = $pdo->prepare(
        "DELETE e FROM expenses e
         JOIN weekly_budgets wb ON e.weekly_budget_id = wb.weekly_budget_id
         JOIN budgets b ON wb.budget_id = b.budget_id
         WHERE e.expense_id = ? AND b.user_id = ?"
    );
    $stmt->execute([$expense_id, $user_id]);
    $success = 'Expense removed.';
}

// All expenses for this month's weeks
$expenses = [];
if (!empty($weekly_budgets)) {
    $week_ids = array_column($weekly_budgets, 'weekly_budget_id');
    $placeholders = implode(',', array_fill(0, count($week_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT e.*, wb.week_number FROM expenses e
         JOIN weekly_budgets wb ON e.weekly_budget_id = wb.weekly_budget_id
         WHERE e.weekly_budget_id IN ($placeholders)
         ORDER BY e.spent_at DESC, e.expense_id DESC"
    );
    $stmt->execute($week_ids);
    $expenses = $stmt->fetchAll();
}

$page_title = 'Expenses';
$active = 'expenses';
require_once 'includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Expenses</h1>
    <p>Log what you actually spent to compare against your estimated shopping list.</p>
  </div>
</div>

<?php foreach ($errors as $e): ?><div class="alert error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
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
  <h2>Log an expense</h2>
  <form method="post">
    <input type="hidden" name="action" value="add_expense">
    <div class="form-row">
      <div>
        <label for="weekly_budget_id">Week</label>
        <select id="weekly_budget_id" name="weekly_budget_id" required>
          <?php foreach ($weekly_budgets as $wb): ?>
            <option value="<?= (int)$wb['weekly_budget_id'] ?>">Week <?= (int)$wb['week_number'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="amount">Amount (₹)</label>
        <input type="number" step="0.01" min="0" id="amount" name="amount" required>
      </div>
      <div>
        <label for="spent_at">Date</label>
        <input type="date" id="spent_at" name="spent_at" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div>
        <label for="description">Description (optional)</label>
        <input type="text" id="description" name="description" placeholder="e.g. Local market run">
      </div>
    </div>
    <button type="submit" class="btn">Log expense</button>
  </form>
</div>

<div class="card">
  <h2>Expense history — <?= date('F Y') ?></h2>
  <?php if (empty($expenses)): ?>
    <div class="empty-state">
      <div class="empty-icon">🧾</div>
      <p>No expenses logged yet this month.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Week</th><th>Description</th><th>Amount</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?= htmlspecialchars($e['spent_at']) ?></td>
            <td>Week <?= (int)$e['week_number'] ?></td>
            <td><?= htmlspecialchars($e['description'] ?: '—') ?></td>
            <td><?= format_money((float)$e['amount']) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this expense?');">
                <input type="hidden" name="action" value="delete_expense">
                <input type="hidden" name="expense_id" value="<?= (int)$e['expense_id'] ?>">
                <button type="submit" class="btn small danger">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
