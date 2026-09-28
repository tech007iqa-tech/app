<?php
// logout.php
// Central Logout Controller for IQA Metal Store
require_once __DIR__ . '/core/StoreAuth.php';

$wasTender = StoreAuth::isTender();
StoreAuth::logout();

$role = $_GET['role'] ?? '';
if ($role === 'tender' || $wasTender) {
    header("Location: tender_login.php?logged_out=1");
    exit;
}

header("Location: index.php");
exit;
