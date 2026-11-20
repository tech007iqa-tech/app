<?php
require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['clear']) || isset($_POST['clear'])) {
    unset($_SESSION['active_customer_id']);
    unset($_SESSION['return_customer_id']);
    unset($_SESSION['last_viewed_order_id']);
    ApiResponse::json(['status' => 'cleared']);
    exit();
}

$customer_id = $_POST['customer_id'] ?? $_GET['customer_id'] ?? '';
if (!empty($customer_id)) {
    $_SESSION['active_customer_id'] = $customer_id;
    ApiResponse::json(['status' => 'success', 'customer_id' => $customer_id]);
} else {
    ApiResponse::json(['status' => 'error', 'message' => 'Missing customer_id']);
}


