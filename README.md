# Smart Grocery & Meal Budget Planner

A PHP + MySQL web app for planning meals against a grocery budget. Set a monthly
budget, split it into weeks, build meals, plan your week, and get a calculated
shopping list that subtracts what's already in your pantry — all costed from
real prices stored in MySQL. (AI features are intentionally deferred — see
`PROJECT_REQUIREMENTS_v2.md`.)

---

## 1. What you need installed

* **XAMPP** (includes Apache + MySQL/MariaDB + phpMyAdmin) — https://www.apachefriends.org
* A code editor (IntelliJ + PHP plugin, VS Code, or similar)

No Composer, no npm, no frameworks — plain PHP with PDO.

---

## 2. Setup steps

### Step 1 — Place the project in XAMPP's web folder

Copy the entire `grocery-planner` folder into XAMPP's `htdocs` directory:

* Windows: `C:\xampp\htdocs\grocery-planner`
* macOS: `/Applications/XAMPP/htdocs/grocery-planner`
* Linux: `/opt/lampp/htdocs/grocery-planner`

### Step 2 — Start Apache and MySQL

Open the XAMPP Control Panel and click **Start** next to both **Apache** and
**MySQL**. Both should turn green.

### Step 3 — Create the database

1. Go to `http://localhost/phpmyadmin`
2. Click **Import** in the top menu
3. Choose the file `database/schema.sql` and click **Go**
   (this creates the `grocery_planner` database and all 12 tables)
4. Click **Import** again, choose `database/seed.sql`, click **Go**
   (this loads ~38 common grocery items with starter prices)

### Step 4 — Check the database connection settings

Open `config/database.php`. The defaults match a fresh XAMPP install:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'grocery_planner');
define('DB_USER', 'root');
define('DB_PASS', '');
```

If your MySQL has a root password set, update `DB_PASS` accordingly.

### Step 5 — Open the site

Visit: `http://localhost/grocery-planner/`

You'll land on the login page. Click **Register** to create your first account.

---

## 3. How to use it (typical flow)

1. **Register** an account, then **log in**.
2. Go to **Budget** → set your monthly amount → split it into weeks.
3. Go to **Grocery Items** → check/edit prices if needed (seed data has ~38 items).
4. Go to **Pantry** → add anything you already have at home.
5. Go to **Meals** → create meals with ingredients (e.g. "Chicken Curry" with
   200g chicken, 1 onion, 2 tomatoes...).
6. Go to **Weekly Planner** → pick a week → assign meals to each day/slot.
7. Go to **Shopping List** → the engine calculates exactly what you need to
   buy (required minus pantry) and its estimated cost, compared to your
   weekly budget.
8. Go to **Expenses** → log what you actually spent as you shop.

---

## 4. Project structure

```
grocery-planner/
├── config/
│   └── database.php         PDO connection settings
├── includes/
│   ├── auth.php              Session/login helpers
│   ├── functions.php         Shared helpers + the grocery calculation engine
│   ├── header.php            Shared page header/nav
│   └── footer.php            Shared page footer
├── assets/
│   ├── css/style.css         All styling
│   └── js/                   (reserved for any future standalone scripts)
├── database/
│   ├── schema.sql            Table definitions
│   └── seed.sql               Starter grocery items + prices
├── index.php                 Redirects to dashboard or login
├── register.php / login.php / logout.php
├── dashboard.php
├── budget.php
├── grocery_items.php
├── pantry.php
├── meals.php
├── meal_planner.php
├── shopping_list.php
├── expenses.php
├── profile.php
└── README.md                 (this file)
```

**Note on structure:** the original architecture doc proposed a `public/` +
`src/` split with per-module controller classes. For a project this size,
this build uses a flatter structure (one file per page, shared logic in
`includes/`) — it's easier to navigate and explain page-by-page in a viva,
while still keeping all database access behind prepared statements and all
money calculations in PHP.

---

## 5. The grocery calculation engine (how it works)

Lives in `includes/functions.php`, function `calculate_shopping_list()`:

1. Reads all meals assigned to the selected week (`meal_plans`)
2. Fetches their ingredients (`meal_ingredients`)
3. Combines duplicate ingredients across meals (e.g. onions needed in 3 meals → summed)
4. Converts everything to each item's default unit (handles g↔kg and ml↔l)
5. Subtracts what's in the user's pantry
6. Whatever remains (never negative) is the shopping quantity
7. Multiplies by the price stored in `grocery_prices` — **never estimated by AI**

This was verified with an automated test using the exact example from the
project spec: 1kg rice required, 400g already in pantry → 600g to buy,
costed correctly.

---

## 6. Testing each feature

| Feature | How to test |
|---|---|
| Register/Login | Create an account, log out, log back in, try a wrong password (should show an error) |
| Budget | Set a monthly amount, add weekly splits — try to over-allocate a week beyond the monthly total (should be blocked) |
| Grocery Items | Add a new item, update an existing price, confirm it reflects on the Shopping List |
| Pantry | Add an item with a quantity, confirm the Shopping List subtracts it correctly |
| Meals | Add a meal with 2–3 ingredients, confirm it appears in the Weekly Planner dropdown |
| Weekly Planner | Assign meals to a few days/slots, save, reload the page — the plan should persist |
| Shopping List | After planning meals, check quantities and costs match manual arithmetic |
| Expenses | Log an expense, confirm it appears in the dashboard's weekly spent total |

You can also inspect table contents directly in phpMyAdmin after any action
to confirm the app is writing the data you expect.

---

## 7. Security notes

* Passwords are hashed with `password_hash()` / verified with `password_verify()`
* All database queries use PDO prepared statements (no SQL injection risk)
* Sessions are used for login state; session ID is regenerated on login
* All page output is escaped with `htmlspecialchars()` to prevent XSS

---

## 8. What's not included (by design)

AI Meal Planner, AI Budget Optimizer, and AI Substitution are deferred — see
`PROJECT_REQUIREMENTS_v2.md`, Section 4A. The database schema already has the
right tables (`meal_plans`, `meal_ingredients`) to support adding AI-assisted
planning later without any restructuring.
