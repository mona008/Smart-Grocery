-- =====================================================================
-- Seed data: common grocery items with sample prices (INR)
-- Run this AFTER schema.sql, on the same grocery_planner database.
-- Prices are examples -- edit them to match real prices any time via
-- the Grocery Items page in the app, or directly in phpMyAdmin.
-- =====================================================================

USE grocery_planner;

INSERT INTO grocery_items (name, category, default_unit) VALUES
                                                             ('Rice', 'Grains', 'kg'),
                                                             ('Wheat Flour', 'Grains', 'kg'),
                                                             ('Bread', 'Grains', 'pcs'),
                                                             ('Oats', 'Grains', 'kg'),
                                                             ('Toor Dal', 'Grains', 'kg'),
                                                             ('Moong Dal', 'Grains', 'kg'),
                                                             ('Milk', 'Dairy', 'l'),
                                                             ('Curd', 'Dairy', 'kg'),
                                                             ('Paneer', 'Dairy', 'kg'),
                                                             ('Butter', 'Dairy', 'kg'),
                                                             ('Cheese', 'Dairy', 'kg'),
                                                             ('Eggs', 'Protein', 'pcs'),
                                                             ('Chicken', 'Protein', 'kg'),
                                                             ('Fish', 'Protein', 'kg'),
                                                             ('Tofu', 'Protein', 'kg'),
                                                             ('Onion', 'Vegetables', 'kg'),
                                                             ('Tomato', 'Vegetables', 'kg'),
                                                             ('Potato', 'Vegetables', 'kg'),
                                                             ('Carrot', 'Vegetables', 'kg'),
                                                             ('Cabbage', 'Vegetables', 'kg'),
                                                             ('Broccoli', 'Vegetables', 'kg'),
                                                             ('Spinach', 'Vegetables', 'kg'),
                                                             ('Capsicum', 'Vegetables', 'kg'),
                                                             ('Cucumber', 'Vegetables', 'kg'),
                                                             ('Garlic', 'Vegetables', 'kg'),
                                                             ('Ginger', 'Vegetables', 'kg'),
                                                             ('Banana', 'Fruits', 'pcs'),
                                                             ('Apple', 'Fruits', 'kg'),
                                                             ('Orange', 'Fruits', 'kg'),
                                                             ('Mango', 'Fruits', 'kg'),
                                                             ('Grapes', 'Fruits', 'kg'),
                                                             ('Salt', 'Spices', 'kg'),
                                                             ('Turmeric Powder', 'Spices', 'kg'),
                                                             ('Red Chili Powder', 'Spices', 'kg'),
                                                             ('Cumin Seeds', 'Spices', 'kg'),
                                                             ('Cooking Oil', 'Other', 'l'),
                                                             ('Sugar', 'Other', 'kg'),
                                                             ('Tea Leaves', 'Other', 'kg');

-- Prices roughly in INR, per default_unit shown above
INSERT INTO grocery_prices (item_id, price_per_unit)
SELECT item_id, price FROM (
                               SELECT 'Rice' AS name, 60 AS price UNION ALL
                               SELECT 'Wheat Flour', 45 UNION ALL
                               SELECT 'Bread', 45 UNION ALL
                               SELECT 'Oats', 120 UNION ALL
                               SELECT 'Toor Dal', 140 UNION ALL
                               SELECT 'Moong Dal', 130 UNION ALL
                               SELECT 'Milk', 60 UNION ALL
                               SELECT 'Curd', 70 UNION ALL
                               SELECT 'Paneer', 350 UNION ALL
                               SELECT 'Butter', 500 UNION ALL
                               SELECT 'Cheese', 450 UNION ALL
                               SELECT 'Eggs', 7 UNION ALL
                               SELECT 'Chicken', 220 UNION ALL
                               SELECT 'Fish', 300 UNION ALL
                               SELECT 'Tofu', 200 UNION ALL
                               SELECT 'Onion', 35 UNION ALL
                               SELECT 'Tomato', 40 UNION ALL
                               SELECT 'Potato', 30 UNION ALL
                               SELECT 'Carrot', 45 UNION ALL
                               SELECT 'Cabbage', 30 UNION ALL
                               SELECT 'Broccoli', 120 UNION ALL
                               SELECT 'Spinach', 30 UNION ALL
                               SELECT 'Capsicum', 60 UNION ALL
                               SELECT 'Cucumber', 30 UNION ALL
                               SELECT 'Garlic', 200 UNION ALL
                               SELECT 'Ginger', 120 UNION ALL
                               SELECT 'Banana', 5 UNION ALL
                               SELECT 'Apple', 180 UNION ALL
                               SELECT 'Orange', 90 UNION ALL
                               SELECT 'Mango', 100 UNION ALL
                               SELECT 'Grapes', 90 UNION ALL
                               SELECT 'Salt', 20 UNION ALL
                               SELECT 'Turmeric Powder', 250 UNION ALL
                               SELECT 'Red Chili Powder', 300 UNION ALL
                               SELECT 'Cumin Seeds', 400 UNION ALL
                               SELECT 'Cooking Oil', 150 UNION ALL
                               SELECT 'Sugar', 45 UNION ALL
                               SELECT 'Tea Leaves', 350
                           ) AS price_data
                               JOIN grocery_items ON grocery_items.name = price_data.name;
