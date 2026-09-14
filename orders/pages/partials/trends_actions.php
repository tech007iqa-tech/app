<?php
/**
 * Trends AJAX Actions Partial Controller
 * Handles backend requests for the B2B Untested Matrix and Tested Market pricing rules.
 */

// 1. Handle B2B Untested Pricing Matrix AJAX Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $matrix_actions = ['update_pricing_matrix', 'add_matrix_row', 'delete_matrix_row', 'add_matrix_category', 'delete_matrix_category'];
    if (in_array($action, $matrix_actions)) {
        ob_clean();
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');

        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['Admin', 'Front Desk'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $conn_wh = Database::warehouse();

        $category_grades = [
            'Regular'    => ['Untested', 'Parts', 'C Grade'],
            'Apple'      => ['Tested', 'Untested', 'For Parts'],
            'Rugged'     => ['Untested Complete', 'Untested Parts', 'Tested Complete', 'Tested No Battery'],
            'Microsoft'  => ['Tested', 'Untested', 'For Parts'],
            'Chromebook' => ['Untested Lot', 'Tested - Clean (A/B)'],
            'Gaming'     => ['Untested', 'Parts', 'C Grade'],
            'RAM'        => ['Untested', 'Tested', 'C Grade'],
            'Storage'    => ['Untested', 'Tested', 'C Grade']
        ];

        try {
            if ($action === 'update_pricing_matrix') {
                $category = trim($input['category'] ?? '');
                $cpu_gen = trim($input['cpu_gen'] ?? '');
                $grade = trim($input['grade'] ?? '');
                $price = isset($input['price']) ? (float)$input['price'] : 0.00;

                if (!empty($category) && !empty($cpu_gen) && !empty($grade)) {
                    $stmt = $conn_wh->prepare("INSERT INTO pricing_rules (category, cpu_gen, grade, price) VALUES (?, ?, ?, ?) ON CONFLICT(category, cpu_gen, grade) DO UPDATE SET price = excluded.price");
                    $stmt->execute([$category, $cpu_gen, $grade, $price]);
                    echo json_encode(['success' => true]);
                    exit();
                }
            } elseif ($action === 'add_matrix_row') {
                $category = trim($input['category'] ?? '');
                $cpu_gen = trim($input['cpu_gen'] ?? '');

                if (!empty($category) && !empty($cpu_gen)) {
                    $grades = $category_grades[$category] ?? ['Untested', 'Tested', 'C Grade'];
                    $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO pricing_rules (category, cpu_gen, grade, price) VALUES (?, ?, ?, 0.00)");
                    foreach ($grades as $g) {
                        $stmt->execute([$category, $cpu_gen, $g]);
                    }
                    echo json_encode(['success' => true]);
                    exit();
                }
            } elseif ($action === 'delete_matrix_row') {
                if ($role !== 'Admin') {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only Admins can delete pricing rows.']);
                    exit();
                }
                $category = trim($input['category'] ?? '');
                $cpu_gen = trim($input['cpu_gen'] ?? '');

                if (!empty($category) && !empty($cpu_gen)) {
                    $stmt = $conn_wh->prepare("DELETE FROM pricing_rules WHERE category = ? AND cpu_gen = ?");
                    $stmt->execute([$category, $cpu_gen]);
                    echo json_encode(['success' => true]);
                    exit();
                }
            } elseif ($action === 'add_matrix_category') {
                $category = trim($input['category'] ?? '');
                $first_row = trim($input['cpu_gen'] ?? 'Default');

                if (!empty($category)) {
                    $grades = $category_grades[$category] ?? ['Untested', 'Tested', 'C Grade'];
                    $stmt = $conn_wh->prepare("INSERT OR IGNORE INTO pricing_rules (category, cpu_gen, grade, price) VALUES (?, ?, ?, 0.00)");
                    foreach ($grades as $g) {
                        $stmt->execute([$category, $first_row, $g]);
                    }
                    echo json_encode(['success' => true]);
                    exit();
                }
            } elseif ($action === 'delete_matrix_category') {
                if ($role !== 'Admin') {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only Admins can delete category tables.']);
                    exit();
                }
                $category = trim($input['category'] ?? '');

                if (!empty($category)) {
                    $stmt = $conn_wh->prepare("DELETE FROM pricing_rules WHERE category = ?");
                    $stmt->execute([$category]);
                    echo json_encode(['success' => true]);
                    exit();
                }
            }
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}

// 2. Handle Tested Market AJAX Endpoints
require_once 'core/TestedMarketManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    if (in_array($action, ['update_tested_market_cell', 'add_tested_market_category', 'delete_tested_market_category', 'add_tested_market_rule', 'delete_tested_market_rule'])) {
        ob_clean();
        if (session_status() === PHP_SESSION_NONE) session_start();
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            if ($action === 'update_tested_market_cell') {
                $rule_id = (int)($input['rule_id'] ?? 0);
                $field = $input['field'] ?? '';
                $value = $input['value'] ?? '';
                if ($field === 'is_2in1' && ($_SESSION['role'] ?? '') !== 'Admin') {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only Admins can modify the 2-in-1 flag.']);
                    exit();
                }
                if ($rule_id > 0 && !empty($field)) {
                    TestedMarketManager::updateRuleField($rule_id, $field, $value);
                    echo json_encode(['success' => true]);
                    exit();
                }
            } elseif ($action === 'add_tested_market_category') {
                $name = trim($input['name'] ?? '');
                $layout = $input['layout_type'] ?? 'laptop';
                if (!empty($name)) {
                    $cat_id = TestedMarketManager::addCategory($name, $layout);
                    echo json_encode(['success' => true, 'category_id' => $cat_id]);
                    exit();
                }
            } elseif ($action === 'delete_tested_market_category') {
                $cat_id = (int)($input['category_id'] ?? 0);
                if ($cat_id > 0) {
                    TestedMarketManager::deleteCategory($cat_id);
                    echo json_encode(['success' => true]);
                    exit();
                }
            } elseif ($action === 'add_tested_market_rule') {
                $cat_id = (int)($input['category_id'] ?? 0);
                if ($cat_id > 0) {
                    $rule_id = TestedMarketManager::addRule($cat_id, $input);
                    echo json_encode(['success' => true, 'rule_id' => $rule_id]);
                    exit();
                }
            } elseif ($action === 'delete_tested_market_rule') {
                if (($_SESSION['role'] ?? '') !== 'Admin') {
                    echo json_encode(['success' => false, 'error' => 'Unauthorized: Only Admins can delete pricing rows.']);
                    exit();
                }
                $rule_id = (int)($input['rule_id'] ?? 0);
                if ($rule_id > 0) {
                    TestedMarketManager::deleteRule($rule_id);
                    echo json_encode(['success' => true]);
                    exit();
                }
            }
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}
