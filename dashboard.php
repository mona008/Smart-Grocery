<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = current_user_id();
$user_name = current_user_name();

$budget = get_active_budget($pdo, $user_id);

$weekly_budgets = [];
$total_allocated = 0;
$total_spent = 0;
$pantry_count = 0;

if ($budget) {

    // Get weekly budgets
    $weekly_budgets = get_weekly_budgets($pdo, $budget['budget_id']);

    // Calculate total spending and allocation
    foreach ($weekly_budgets as &$wb) {
        $wb['spent'] = get_weekly_spent(
            $pdo,
            $wb['weekly_budget_id']
        );

        $total_allocated += (float) $wb['allocated_amount'];
        $total_spent += $wb['spent'];
    }

    unset($wb);

    // Count pantry items
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS c
         FROM pantry
         WHERE user_id = ?"
    );

    $stmt->execute([$user_id]);

    $pantry_count = (int) $stmt->fetch()['c'];
}

// ------------------------------------------------------------
// Budget calculations
// ------------------------------------------------------------

$monthly_budget = $budget
    ? (float) $budget['monthly_amount']
    : 0;

$remaining = $monthly_budget - $total_spent;

$budget_percentage = $monthly_budget > 0
    ? ($total_spent / $monthly_budget) * 100
    : 0;

$budget_percentage_display = min(
    100,
    max(0, $budget_percentage)
);

// Determine budget status
if ($total_spent > $monthly_budget && $monthly_budget > 0) {

    $budget_status = 'over';
    $budget_status_text = 'Over budget';

} elseif ($budget_percentage >= 80) {

    $budget_status = 'warn';
    $budget_status_text = 'Almost at limit';

} else {

    $budget_status = 'ok';
    $budget_status_text = 'On track';
}

// Current month name
$current_month = date('F Y');

$page_title = 'Dashboard';
$active = 'dashboard';

require_once 'includes/header.php';
?>

<!-- ============================================================
     DASHBOARD HERO
     ============================================================ -->

<section class="dashboard-hero">

    <div class="dashboard-hero-content">

        <div class="dashboard-greeting">
            <span class="greeting-icon">👋</span>

            <div>
                <p class="greeting-small">
                    <?= htmlspecialchars($current_month) ?>
                </p>

                <h1>
                    Hi <?= htmlspecialchars($user_name) ?>!
                </h1>
            </div>
        </div>

        <?php if ($budget): ?>

            <p class="dashboard-subtitle">
                Here's your grocery budget at a glance.
            </p>

            <div class="hero-budget-row">

                <div>
                    <span class="hero-label">
                        Remaining this month
                    </span>

                    <div class="hero-amount">
                        <?= format_money($remaining) ?>
                    </div>
                </div>

                <div class="hero-mini-info">
                    <span>Monthly Budget</span>
                    <strong>
                        <?= format_money($monthly_budget) ?>
                    </strong>
                </div>

                <div class="hero-mini-info">
                    <span>Spent</span>
                    <strong>
                        <?= format_money($total_spent) ?>
                    </strong>
                </div>

            </div>

        <?php else: ?>

            <p class="dashboard-subtitle">
                You haven't created a grocery budget for this month yet.
            </p>

            <a href="budget.php" class="btn hero-action">
                💰 Set Monthly Budget
            </a>

        <?php endif; ?>

    </div>

</section>


<?php if ($budget): ?>

<!-- ============================================================
     STAT CARDS
     ============================================================ -->

<div class="dashboard-stats">

    <!-- Monthly Budget -->
    <div class="dashboard-stat">

        <div class="dashboard-stat-icon orange">
            💰
        </div>

        <div>
            <div class="dashboard-stat-label">
                Monthly Budget
            </div>

            <div class="dashboard-stat-value">
                <?= format_money($monthly_budget) ?>
            </div>
        </div>

    </div>


    <!-- Total Spent -->
    <div class="dashboard-stat">

        <div class="dashboard-stat-icon red">
            💸
        </div>

        <div>
            <div class="dashboard-stat-label">
                Total Spent
            </div>

            <div class="dashboard-stat-value">
                <?= format_money($total_spent) ?>
            </div>
        </div>

    </div>


    <!-- Pantry -->
    <div class="dashboard-stat">

        <div class="dashboard-stat-icon green">
            🥫
        </div>

        <div>
            <div class="dashboard-stat-label">
                Pantry Items
            </div>

            <div class="dashboard-stat-value">
                <?= $pantry_count ?>
            </div>
        </div>

    </div>


    <!-- Remaining -->
    <div class="dashboard-stat highlight">

        <div class="dashboard-stat-icon white">
            🏦
        </div>

        <div>
            <div class="dashboard-stat-label">
                Remaining
            </div>

            <div class="dashboard-stat-value">
                <?= format_money($remaining) ?>
            </div>
        </div>

    </div>

</div>


<!-- ============================================================
     BUDGET PROGRESS + QUICK ACTIONS
     ============================================================ -->

<div class="dashboard-two-column">

    <!-- Budget Progress -->
    <section class="card budget-progress-card">

        <div class="section-heading">

            <div>
                <p class="section-kicker">
                    MONTHLY OVERVIEW
                </p>

                <h2>
                    Budget Progress
                </h2>
            </div>

            <span class="badge <?= $budget_status ?>">
                <?= $budget_status_text ?>
            </span>

        </div>


        <div class="budget-progress-number">
            <?= number_format($budget_percentage, 0) ?>%
        </div>

        <div class="progress-track dashboard-progress">

            <div
                class="progress-fill <?= $budget_status ?>"
                style="width: <?= $budget_percentage_display ?>%;">
            </div>

        </div>

        <div class="budget-progress-details">

            <span>
                <?= format_money($total_spent) ?> spent
            </span>

            <span>
                <?= format_money($monthly_budget) ?> budget
            </span>

        </div>


        <?php if ($remaining >= 0): ?>

            <div class="budget-message success-message">
                ✅ You have
                <strong><?= format_money($remaining) ?></strong>
                left for this month.
            </div>

        <?php else: ?>

            <div class="budget-message danger-message">
                ⚠️ You are
                <strong><?= format_money(abs($remaining)) ?></strong>
                over your monthly budget.
            </div>

        <?php endif; ?>

    </section>


    <!-- Quick Actions -->
    <section class="card quick-actions-card">

        <div class="section-heading">

            <div>
                <p class="section-kicker">
                    SHORTCUTS
                </p>

                <h2>
                    Quick Actions
                </h2>
            </div>

        </div>


        <div class="quick-actions">

            <a href="grocery_items.php" class="quick-action">

                <span class="quick-icon">
                    🛒
                </span>

                <span>
                    <strong>Grocery Items</strong>
                    <small>Manage products</small>
                </span>

                <span class="quick-arrow">
                    →
                </span>

            </a>


            <a href="pantry.php" class="quick-action">

                <span class="quick-icon">
                    🥫
                </span>

                <span>
                    <strong>My Pantry</strong>
                    <small>Track what you have</small>
                </span>

                <span class="quick-arrow">
                    →
                </span>

            </a>


            <a href="meals.php" class="quick-action">

                <span class="quick-icon">
                    🍲
                </span>

                <span>
                    <strong>Meals</strong>
                    <small>Create your meals</small>
                </span>

                <span class="quick-arrow">
                    →
                </span>

            </a>


            <a href="meal_planner.php" class="quick-action">

                <span class="quick-icon">
                    📅
                </span>

                <span>
                    <strong>Weekly Planner</strong>
                    <small>Plan your week</small>
                </span>

                <span class="quick-arrow">
                    →
                </span>

            </a>


            <a href="shopping_list.php" class="quick-action">

                <span class="quick-icon">
                    🛍️
                </span>

                <span>
                    <strong>Shopping List</strong>
                    <small>See what to buy</small>
                </span>

                <span class="quick-arrow">
                    →
                </span>

            </a>


            <a href="expenses.php" class="quick-action">

                <span class="quick-icon">
                    💳
                </span>

                <span>
                    <strong>Expenses</strong>
                    <small>Track spending</small>
                </span>

                <span class="quick-arrow">
                    →
                </span>

            </a>

        </div>

    </section>

</div>


<!-- ============================================================
     WEEKLY BUDGETS
     ============================================================ -->

<section class="card weekly-dashboard-card">

    <div class="section-heading">

        <div>
            <p class="section-kicker">
                WEEKLY PLAN
            </p>

            <h2>
                This Month's Weeks
            </h2>
        </div>

        <a href="budget.php" class="btn small secondary">
            Manage Budget
        </a>

    </div>


    <?php if (empty($weekly_budgets)): ?>

        <div class="empty-state">

            <div class="empty-icon">
                📅
            </div>

            <p>
                You haven't divided your monthly budget into weeks yet.
            </p>

            <a href="budget.php" class="btn">
                Split Budget Into Weeks
            </a>

        </div>

    <?php else: ?>

        <div class="weekly-list">

            <?php foreach ($weekly_budgets as $wb):

                $allocated = (float) $wb['allocated_amount'];
                $spent = (float) $wb['spent'];

                $pct = $allocated > 0
                    ? ($spent / $allocated) * 100
                    : 0;

                $display_pct = min(
                    100,
                    max(0, $pct)
                );

                if ($spent > $allocated) {

                    $status = 'over';
                    $status_label = 'Over budget';

                } elseif ($pct >= 80) {

                    $status = 'warn';
                    $status_label = 'Almost there';

                } else {

                    $status = 'ok';
                    $status_label = 'On track';
                }

            ?>

                <div class="weekly-item">

                    <div class="weekly-top">

                        <div class="weekly-title">

                            <span class="week-number">
                                Week <?= (int) $wb['week_number'] ?>
                            </span>

                            <span class="badge <?= $status ?>">
                                <?= $status_label ?>
                            </span>

                        </div>

                        <strong>
                            <?= format_money($spent) ?>
                            /
                            <?= format_money($allocated) ?>
                        </strong>

                    </div>


                    <div class="progress-track">

                        <div
                            class="progress-fill <?= $status ?>"
                            style="width: <?= $display_pct ?>%;">
                        </div>

                    </div>


                    <div class="weekly-bottom">

                        <span>
                            <?= number_format($pct, 0) ?>% used
                        </span>

                        <?php if ($allocated - $spent >= 0): ?>

                            <span>
                                <?= format_money($allocated - $spent) ?>
                                left
                            </span>

                        <?php else: ?>

                            <span class="text-danger">
                                <?= format_money(abs($allocated - $spent)) ?>
                                over
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <div class="dashboard-bottom-actions">

            <a href="meal_planner.php" class="btn secondary">
                📅 Weekly Planner
            </a>

            <a href="shopping_list.php" class="btn secondary">
                🛍️ Shopping List
            </a>

        </div>

    <?php endif; ?>

</section>


<?php endif; ?>


<?php require_once 'includes/footer.php'; ?>