<?php
/**
 * Marketing Hub - Main Front Controller & Modular Dispatcher
 * Dispatches requests to isolated, self-contained modules.
 */

require_once __DIR__ . '/config.php';
require_once INCLUDES_PATH . '/db.php';

// Global shared database connection handles
$marketingDb = get_marketing_db();
$warehouseDb = get_warehouse_db();
$labelsDb = get_labels_db();
$crmDb = get_master_crm_db();

// Strict Module Registry
$allowed_modules = [
    'dashboard'       => 'Dashboard',
    'leads'           => 'Leads',
    'model_templates' => 'Templates',
    'ad_generator'    => 'Ad Generator',
    'campaigns'       => 'Campaigns',
    'photo_bucket'    => 'Photo Bucket',
    'reports'         => 'Reports',
    'docs'            => 'Docs'
];

$page = $_GET['page'] ?? 'dashboard';

// Enforce whitelist to prevent directory traversal or arbitrary inclusions
if (!array_key_exists($page, $allowed_modules)) {
    $page = 'dashboard';
}

$module_path = MODULES_PATH . '/' . $page . '/index.php';

include_once INCLUDES_PATH . '/header.php';

echo '<main id="main-content">';

if (file_exists($module_path)) {
    include $module_path;
} else {
    echo '<section class="card" style="padding: 3rem; text-align: center;"><h2>404 - Module Not Found</h2><p style="color: var(--text-dim);">The module <strong>' . h($page) . '</strong> could not be located on the filesystem.</p><a href="?page=dashboard" class="btn-action" style="margin-top: 1rem; display: inline-block;">Return to Dashboard</a></section>';
}

echo '</main>';

include_once INCLUDES_PATH . '/footer.php';
?>
