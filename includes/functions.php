<?php
/**
 * Shared helper functions
 * ------------------------
 * Includes the core Grocery Calculation Engine (the heart of the app):
 * meals -> ingredients -> combine duplicates -> subtract pantry ->
 * shopping list, with cost calculated from MySQL prices (never from AI).
 */

function format_money(float $amount): string {
    return '₹' . number_format($amount, 2);
}

function day_name(int $day): string {
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
              5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    return $days[$day] ?? 'Unknown';
}

/**
 * Convert a quantity between compatible units.
 * Only g<->kg and ml<->l are convertible; 'pcs' has no conversion.
 * If units aren't compatible, returns the quantity unchanged (best effort).
 */
function convert_qty(float $qty, string $from, string $to): float {
    if ($from === $to) return $qty;
    $weight = ['g' => 1, 'kg' => 1000];
    $volume = ['ml' => 1, 'l' => 1000];
    if (isset($weight[$from]) && isset($weight[$to])) {
        return $qty * $weight[$from] / $weight[$to];
    }
    if (isset($volume[$from]) && isset($volume[$to])) {
        return $qty * $volume[$from] / $volume[$to];
    }
    return $qty;
}

/** Get (or create) this month's budget row for a user. */
function get_active_budget(PDO $pdo, int $user_id): ?array {
    $stmt = $pdo->prepare(
        "SELECT * FROM budgets WHERE user_id = ? AND month = ? LIMIT 1"
    );
    $firstOfMonth = date('Y-m-01');
    $stmt->execute([$user_id, $firstOfMonth]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Get all weekly budgets for a given monthly budget, in week order. */
function get_weekly_budgets(PDO $pdo, int $budget_id): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM weekly_budgets WHERE budget_id = ? ORDER BY week_number"
    );
    $stmt->execute([$budget_id]);
    return $stmt->fetchAll();
}

/** Get a single weekly budget, but only if it belongs to this user (security check). */
function get_weekly_budget_for_user(PDO $pdo, int $weekly_budget_id, int $user_id): ?array {
    $stmt = $pdo->prepare(
        "SELECT wb.* FROM weekly_budgets wb
         JOIN budgets b ON wb.budget_id = b.budget_id
         WHERE wb.weekly_budget_id = ? AND b.user_id = ?"
    );
    $stmt->execute([$weekly_budget_id, $user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Sum of allocated_amount across a user's current weekly budgets. */
function sum_allocated(PDO $pdo, int $budget_id): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(allocated_amount),0) AS total FROM weekly_budgets WHERE budget_id = ?"
    );
    $stmt->execute([$budget_id]);
    return (float) $stmt->fetch()['total'];
}

/** Total actual spending logged against a weekly budget. */
function get_weekly_spent(PDO $pdo, int $weekly_budget_id): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE weekly_budget_id = ?"
    );
    $stmt->execute([$weekly_budget_id]);
    return (float) $stmt->fetch()['total'];
}

/** Get a user's full pantry, keyed by item_id for fast lookup. */
function get_pantry_map(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare(
        "SELECT item_id, quantity, unit FROM pantry WHERE user_id = ?"
    );
    $stmt->execute([$user_id]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['item_id']] = $row;
    }
    return $map;
}

/**
 * THE GROCERY CALCULATION ENGINE
 * --------------------------------
 * Steps (matches the project spec exactly):
 *   1. Read all meals planned for the week
 *   2. Find their ingredients
 *   3. Combine duplicate ingredients (same item across multiple meals)
 *   4. Calculate total required quantity
 *   5. Compare with pantry inventory
 *   6. Subtract available pantry quantities
 *   7. Generate only the required shopping quantity
 * Cost is calculated afterward using grocery_prices from MySQL only.
 *
 * Returns ['items' => [...], 'total' => float]
 */
function calculate_shopping_list(PDO $pdo, int $weekly_budget_id, int $user_id): array {
    // Step 1: all meals planned for this week
    $stmt = $pdo->prepare(
        "SELECT DISTINCT meal_id FROM meal_plans WHERE weekly_budget_id = ?"
    );
    $stmt->execute([$weekly_budget_id]);
    $meal_ids = array_column($stmt->fetchAll(), 'meal_id');

    if (empty($meal_ids)) {
        return ['items' => [], 'total' => 0.0];
    }

    // Step 2 + 3 + 4: ingredients for those meals, combined per item
    $placeholders = implode(',', array_fill(0, count($meal_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT mi.item_id, mi.quantity, mi.unit,
                gi.name, gi.category, gi.default_unit
         FROM meal_ingredients mi
         JOIN grocery_items gi ON mi.item_id = gi.item_id
         WHERE mi.meal_id IN ($placeholders)"
    );
    $stmt->execute($meal_ids);

    $required = []; // item_id => ['name'=>, 'category'=>, 'unit'=>default_unit, 'qty'=>float]
    foreach ($stmt->fetchAll() as $row) {
        $qty_in_default = convert_qty((float)$row['quantity'], $row['unit'], $row['default_unit']);
        if (!isset($required[$row['item_id']])) {
            $required[$row['item_id']] = [
                'name'     => $row['name'],
                'category' => $row['category'],
                'unit'     => $row['default_unit'],
                'qty'      => 0.0,
            ];
        }
        $required[$row['item_id']]['qty'] += $qty_in_default;
    }

    // Step 5 + 6: subtract pantry
    $pantry = get_pantry_map($pdo, $user_id);

    // Prices, fetched once
    $stmt = $pdo->query("SELECT item_id, price_per_unit FROM grocery_prices");
    $prices = [];
    foreach ($stmt->fetchAll() as $row) {
        $prices[$row['item_id']] = (float) $row['price_per_unit'];
    }

    $items = [];
    $total = 0.0;

    foreach ($required as $item_id => $info) {
        $pantry_qty = 0.0;
        if (isset($pantry[$item_id])) {
            $pantry_qty = convert_qty((float)$pantry[$item_id]['quantity'], $pantry[$item_id]['unit'], $info['unit']);
        }

        // Step 7: only the required shopping quantity (never negative)
        $shopping_qty = max(0.0, $info['qty'] - $pantry_qty);

        if ($shopping_qty <= 0.0001) {
            continue; // fully covered by pantry, nothing to buy
        }

        $price = $prices[$item_id] ?? 0.0;
        $cost = round($shopping_qty * $price, 2);
        $total += $cost;

        $items[] = [
            'item_id'       => $item_id,
            'name'          => $info['name'],
            'category'      => $info['category'],
            'unit'          => $info['unit'],
            'required_qty'  => round($info['qty'], 2),
            'pantry_qty'    => round($pantry_qty, 2),
            'shopping_qty'  => round($shopping_qty, 2),
            'price_per_unit'=> $price,
            'estimated_cost'=> $cost,
        ];
    }

    // Sort alphabetically by category then name for a tidy display
    usort($items, fn($a, $b) => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);

    return ['items' => $items, 'total' => round($total, 2)];
}

/** Save a calculated shopping list into the database (replacing any previous one for that week). */
function save_shopping_list(PDO $pdo, int $weekly_budget_id, array $result): int {
    // Remove any previously generated list for this week (keeps one active list per week)
    $stmt = $pdo->prepare("DELETE FROM shopping_lists WHERE weekly_budget_id = ?");
    $stmt->execute([$weekly_budget_id]);

    $stmt = $pdo->prepare(
        "INSERT INTO shopping_lists (weekly_budget_id, estimated_total) VALUES (?, ?)"
    );
    $stmt->execute([$weekly_budget_id, $result['total']]);
    $list_id = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO shopping_list_items (list_id, item_id, quantity_needed, unit, estimated_cost)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($result['items'] as $item) {
        $stmt->execute([$list_id, $item['item_id'], $item['shopping_qty'], $item['unit'], $item['estimated_cost']]);
    }

    return $list_id;
}
