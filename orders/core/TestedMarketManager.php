<?php
require_once __DIR__ . '/database.php';

/**
 * Tested Market Pricing Manager Core Service
 * Handles tested market categories, pricing rules, AJAX field updates, and derived tier calculations.
 */
class TestedMarketManager {
    /**
     * Fetch all active categories ordered by display_order.
     * @return array
     */
    public static function getCategories() {
        $db = Database::warehouse();
        return $db->query("SELECT * FROM tested_market_categories ORDER BY display_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all rules for a specific category ID.
     * @param int $category_id
     * @return array
     */
    public static function getRulesByCategory($category_id) {
        $db = Database::warehouse();
        $stmt = $db->prepare("SELECT * FROM tested_market_rules WHERE category_id = ? ORDER BY id ASC");
        $stmt->execute([(int)$category_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a new category tab.
     * @param string $name
     * @param string $layout_type ('laptop' or 'ram')
     * @return int Inserted Category ID
     */
    public static function addCategory($name, $layout_type = 'laptop') {
        $db = Database::warehouse();
        $name = trim($name);
        if (empty($name)) return false;

        $max_order = $db->query("SELECT MAX(display_order) FROM tested_market_categories")->fetchColumn();
        $display_order = ($max_order !== false) ? ((int)$max_order + 1) : 1;

        $stmt = $db->prepare("INSERT INTO tested_market_categories (name, display_order, layout_type) VALUES (?, ?, ?)");
        $stmt->execute([$name, $display_order, $layout_type]);
        return $db->lastInsertId();
    }

    /**
     * Delete a category tab and all its associated rules.
     * @param int $category_id
     * @return bool
     */
    public static function deleteCategory($category_id) {
        $db = Database::warehouse();
        $stmt = $db->prepare("DELETE FROM tested_market_categories WHERE id = ?");
        return $stmt->execute([(int)$category_id]);
    }

    /**
     * Add a new pricing rule to a category.
     * @param int $category_id
     * @param array $data
     * @return int Inserted Rule ID
     */
    public static function addRule($category_id, $data) {
        $db = Database::warehouse();
        $stmt = $db->prepare("INSERT INTO tested_market_rules (category_id, brand_series, model_number, is_2in1, cpu, price, sale_through, sold_count, effective_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (int)$category_id,
            $data['brand_series'] ?? '',
            $data['model_number'] ?? '',
            !empty($data['is_2in1']) ? 1 : 0,
            $data['cpu'] ?? '',
            (float)($data['price'] ?? 0.00),
            (float)($data['sale_through'] ?? 0.00),
            (int)($data['sold_count'] ?? 0),
            $data['effective_date'] ?? date('n/j')
        ]);
        return $db->lastInsertId();
    }

    /**
     * Update a single cell/field of a rule via AJAX.
     * @param int $rule_id
     * @param string $field
     * @param mixed $value
     * @return bool
     */
    public static function updateRuleField($rule_id, $field, $value) {
        $db = Database::warehouse();
        $allowed_fields = ['brand_series', 'model_number', 'is_2in1', 'cpu', 'price', 'sale_through', 'sold_count', 'effective_date'];
        if (!in_array($field, $allowed_fields)) {
            throw new Exception("Invalid field name: " . $field);
        }

        if (in_array($field, ['price', 'sale_through'])) {
            $value = (float)$value;
        } elseif ($field === 'is_2in1' || $field === 'sold_count') {
            $value = (int)$value;
        } else {
            $value = trim($value);
        }

        $stmt = $db->prepare("UPDATE tested_market_rules SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$value, (int)$rule_id]);
    }

    /**
     * Delete a rule row.
     * @param int $rule_id
     * @return bool
     */
    public static function deleteRule($rule_id) {
        $db = Database::warehouse();
        $stmt = $db->prepare("DELETE FROM tested_market_rules WHERE id = ?");
        return $stmt->execute([(int)$rule_id]);
    }

    /**
     * Compute tier formulas derived from Price and Sale Through %
     *
     * Formulas:
     * Full Specs = (Price * 0.92) - 95
     * Opportunity (Full Specs) = Sale Through * Full Specs
     * Boot2BIOS = (Price * 0.92) - 55
     * Opportunity (Boot2BIOS) = Sale Through * Boot2BIOS
     *
     * @param float $price
     * @param float $sale_through Decimal value (e.g. 0.4718 for 47.18%)
     * @return array
     */
    public static function calculateTiers($price, $sale_through) {
        $price = (float)$price;
        $sale_through = (float)$sale_through;

        if ($price <= 0) {
            return [
                'full_specs' => 0.00,
                'opp_full_specs' => 0.00,
                'boot2bios' => 0.00,
                'opp_boot2bios' => 0.00
            ];
        }

        $full_specs = max(0.00, ($price * 0.92) - 95.00);
        $opp_full_specs = $sale_through * $full_specs;
        $boot2bios = max(0.00, ($price * 0.92) - 55.00);
        $opp_boot2bios = $sale_through * $boot2bios;

        return [
            'full_specs' => round($full_specs, 2),
            'opp_full_specs' => round($opp_full_specs, 2),
            'boot2bios' => round($boot2bios, 2),
            'opp_boot2bios' => round($opp_boot2bios, 2)
        ];
    }
}
