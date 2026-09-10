<?php
// Expects $page_title to be set by the including page, and optionally $active (nav key)
if (!isset($page_title)) { $page_title = 'Smart Grocery & Meal Budget Planner'; }
if (!isset($active)) { $active = ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> · Smart Grocery Planner</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (is_logged_in()): ?>
<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="dashboard.php">
      <span class="brand-mark">🥕</span>
      <span class="brand-text">Smart Grocery Planner</span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <nav class="main-nav" id="mainNav">
      <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="budget.php" class="<?= $active === 'budget' ? 'active' : '' ?>">Budget</a>
      <a href="grocery_items.php" class="<?= $active === 'grocery' ? 'active' : '' ?>">Grocery Items</a>
      <a href="pantry.php" class="<?= $active === 'pantry' ? 'active' : '' ?>">Pantry</a>
      <a href="meals.php" class="<?= $active === 'meals' ? 'active' : '' ?>">Meals</a>
      <a href="meal_planner.php" class="<?= $active === 'planner' ? 'active' : '' ?>">Weekly Planner</a>
      <a href="shopping_list.php" class="<?= $active === 'shopping' ? 'active' : '' ?>">Shopping List</a>
      <a href="expenses.php" class="<?= $active === 'expenses' ? 'active' : '' ?>">Expenses</a>
      <a href="profile.php" class="<?= $active === 'profile' ? 'active' : '' ?>">Profile</a>
      <a href="logout.php" class="nav-logout">Logout</a>
    </nav>
  </div>
</header>
<?php endif; ?>

<main class="page-wrap">
