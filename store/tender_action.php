<?php
// tender_action.php - Central Action Controller for Store Tenders
require_once __DIR__ . '/core/Tender.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/Inventory.php';

require_once __DIR__ . '/core/StoreAuth.php';

// 1. Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    StoreAuth::logout();
    header('Location: tender_login.php');
    exit;
}

// 2. Enforce Tender Authentication
if (!Tender::isLoggedIn()) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Store Tender privileges required.']);
        exit;
    }
    header('Location: ' . Tender::loginUrl('/store/'));
    exit;
}

$inventory = new Inventory($db);

// 3. AJAX: Fetch warehouse stock for the tender drawer
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_warehouse_items') {
    header('Content-Type: application/json');
    $sector = $_GET['sector'] ?? null;
    $search = $_GET['search'] ?? null;
    $items = $inventory->getWarehouseAvailableProducts($sector, $search);
    echo json_encode(['success' => true, 'items' => array_values($items)]);
    exit;
}

// 4. POST Actions: post_warehouse, unpost, add, edit, delete
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $file = $_FILES['photo'] ?? null;
    $id = (int)($_POST['id'] ?? 0);

    try {
        switch ($action) {
            case 'post_warehouse':
                if ($id > 0) {
                    $inventory->postFromWarehouse($id, $_POST, $file);
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Product posted to store!']);
                        exit;
                    }
                } else {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid or missing product ID.']);
                        exit;
                    }
                }
                break;

            case 'unpost':
                if ($id > 0) {
                    $inventory->unpostProduct($id);
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Product unposted from store.']);
                        exit;
                    }
                } else {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Invalid product ID to unpost.']);
                        exit;
                    }
                }
                break;

            case 'add':
                $inventory->add($_POST, $file);
                break;

            case 'edit':
                if ($id > 0) {
                    $inventory->update($id, $_POST, $file);
                }
                break;

            case 'delete':
                if ($id > 0) {
                    $inventory->delete($id);
                }
                break;
        }
    } catch (Throwable $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $referer);
    exit;
}

header('Location: index.php');
exit;
