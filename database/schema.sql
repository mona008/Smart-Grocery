-- =====================================================================
-- Smart Grocery & Meal Budget Planner - Database Schema
-- =====================================================================
-- How to use this file:
--   1. Open phpMyAdmin (http://localhost/phpmyadmin) with XAMPP running
--   2. Create a new database called: grocery_planner
--   3. Select that database, click the "Import" tab
--   4. Choose this file and click "Go"
--   OR run from the MySQL command line:
--   mysql -u root -p grocery_planner < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS grocery_planner
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE grocery_planner;

-- ---------------------------------------------------------------------
-- 1. users
-- One row per registered user. Passwords are hashed, never stored plain.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. budgets
-- One row per user per month. "month" is stored as the 1st of that month
-- (e.g. 2026-09-01) so it's easy to query/compare.
-- ---------------------------------------------------------------------
CREATE TABLE budgets (
    budget_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    month          DATE NOT NULL,
    monthly_amount DECIMAL(10,2) NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_month (user_id, month)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. weekly_budgets
-- Splits a monthly budget into weeks (1-4, sometimes 5).
-- The application must enforce: SUM(allocated_amount) <= monthly_amount
-- ---------------------------------------------------------------------
CREATE TABLE weekly_budgets (
    weekly_budget_id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id        INT NOT NULL,
    week_number      TINYINT NOT NULL,
    allocated_amount DECIMAL(10,2) NOT NULL,
    spent_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE,
    UNIQUE KEY unique_budget_week (budget_id, week_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. grocery_items
-- Master catalog of grocery items (shared across all users).
-- ---------------------------------------------------------------------
CREATE TABLE grocery_items (
    item_id      INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    category     ENUM('Fruits','Vegetables','Grains','Dairy','Protein','Spices','Other') NOT NULL,
    default_unit ENUM('g','kg','ml','l','pcs') NOT NULL,
    UNIQUE KEY unique_item_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. grocery_prices
-- Current price per unit for each item. Kept as its own table (instead of
-- a column on grocery_items) so price history/updates stay clean and
-- separate from the item's identity.
-- ---------------------------------------------------------------------
CREATE TABLE grocery_prices (
    price_id       INT AUTO_INCREMENT PRIMARY KEY,
    item_id        INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES grocery_items(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_price (item_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. pantry
-- What a specific user currently has at home. Junction between users and
-- grocery_items, with a quantity.
-- ---------------------------------------------------------------------
CREATE TABLE pantry (
    pantry_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    item_id    INT NOT NULL,
    quantity   DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit       ENUM('g','kg','ml','l','pcs') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES grocery_items(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_item (user_id, item_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. meals
-- A reusable meal a user has defined (e.g. "Chicken Curry").
-- ---------------------------------------------------------------------
CREATE TABLE meals (
    meal_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id  INT NOT NULL,
    name     VARCHAR(150) NOT NULL,
    category ENUM('Breakfast','Lunch','Dinner','Snack') NOT NULL,
    servings SMALLINT NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. meal_ingredients
-- Junction table: which grocery items (and how much) a meal needs.
-- This resolves the many-to-many relationship between meals and
-- grocery_items.
-- ---------------------------------------------------------------------
CREATE TABLE meal_ingredients (
    meal_ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    meal_id            INT NOT NULL,
    item_id            INT NOT NULL,
    quantity           DECIMAL(10,2) NOT NULL,
    unit               ENUM('g','kg','ml','l','pcs') NOT NULL,
    FOREIGN KEY (meal_id) REFERENCES meals(meal_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES grocery_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. meal_plans
-- Assigns a meal to a specific day + slot within a specific week.
-- ---------------------------------------------------------------------
CREATE TABLE meal_plans (
    meal_plan_id     INT AUTO_INCREMENT PRIMARY KEY,
    weekly_budget_id INT NOT NULL,
    day_of_week      TINYINT NOT NULL,  -- 1 = Monday ... 7 = Sunday
    meal_type        ENUM('Breakfast','Lunch','Dinner','Snack') NOT NULL,
    meal_id          INT NOT NULL,
    FOREIGN KEY (weekly_budget_id) REFERENCES weekly_budgets(weekly_budget_id) ON DELETE CASCADE,
    FOREIGN KEY (meal_id) REFERENCES meals(meal_id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (weekly_budget_id, day_of_week, meal_type)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. shopping_lists
-- One generated list per week, storing the total estimated cost.
-- ---------------------------------------------------------------------
CREATE TABLE shopping_lists (
    list_id          INT AUTO_INCREMENT PRIMARY KEY,
    weekly_budget_id INT NOT NULL,
    generated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estimated_total  DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (weekly_budget_id) REFERENCES weekly_budgets(weekly_budget_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. shopping_list_items
-- Line items of a generated shopping list.
-- ---------------------------------------------------------------------
CREATE TABLE shopping_list_items (
    list_item_id     INT AUTO_INCREMENT PRIMARY KEY,
    list_id          INT NOT NULL,
    item_id          INT NOT NULL,
    quantity_needed  DECIMAL(10,2) NOT NULL,
    unit             ENUM('g','kg','ml','l','pcs') NOT NULL,
    estimated_cost   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (list_id) REFERENCES shopping_lists(list_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES grocery_items(item_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. expenses
-- Actual amounts the user has logged as spent, per week.
-- ---------------------------------------------------------------------
CREATE TABLE expenses (
    expense_id       INT AUTO_INCREMENT PRIMARY KEY,
    weekly_budget_id INT NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    description      VARCHAR(255),
    spent_at         DATE NOT NULL,
    FOREIGN KEY (weekly_budget_id) REFERENCES weekly_budgets(weekly_budget_id) ON DELETE CASCADE
) ENGINE=InnoDB;
