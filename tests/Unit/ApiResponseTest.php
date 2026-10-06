<?php
/**
 * Universal AJAX & API Response Subsystem Tests
 */

require_once __DIR__ . '/../../core/ApiResponse.php';
require_once __DIR__ . '/../../core/Security.php';

TestRunner::suite('AJAX Engine & ApiResponse Subsystem', function() {

    TestRunner::test('ApiResponse class exists and exposes standard contract methods', function() {
        Assert::true(class_exists('ApiResponse'), 'ApiResponse class must exist');
        Assert::true(method_exists('ApiResponse', 'json'), 'ApiResponse::json must exist');
        Assert::true(method_exists('ApiResponse', 'success'), 'ApiResponse::success must exist');
        Assert::true(method_exists('ApiResponse', 'error'), 'ApiResponse::error must exist');
        Assert::true(method_exists('ApiResponse', 'requireCsrf'), 'ApiResponse::requireCsrf must exist');
        Assert::true(method_exists('ApiResponse', 'requireAuth'), 'ApiResponse::requireAuth must exist');
        Assert::true(method_exists('ApiResponse', 'getJsonInput'), 'ApiResponse::getJsonInput must exist');
    });

    TestRunner::test('ApiResponse::getJsonInput parses input and handles empty stream safely', function() {
        $result = ApiResponse::getJsonInput();
        Assert::true(is_array($result), 'getJsonInput must always return an array');
    });

    TestRunner::test('Universal AppSync client asset is accessible and non-empty', function() {
        $rootAsset = __DIR__ . '/../../assets/js/app_sync.js';
        $ordersAsset = __DIR__ . '/../../orders/assets/js/app_sync.js';

        Assert::true(file_exists($rootAsset), 'Root assets/js/app_sync.js must exist');
        Assert::true(file_exists($ordersAsset), 'orders/assets/js/app_sync.js must exist');

        $content = file_get_contents($rootAsset);
        Assert::true(str_contains($content, 'AppSync'), 'app_sync.js must define AppSync client');
        Assert::true(str_contains($content, 'applyDiff'), 'app_sync.js must contain smart DOM diffing engine');
        Assert::true(str_contains($content, 'checkSync'), 'app_sync.js must contain checkSync polling');
    });

    TestRunner::test('Leads and Orders controllers connect AppSync live registration without location.reload() anti-pattern', function() {
        $leadsJs = file_get_contents(__DIR__ . '/../../orders/assets/js/leads.js');
        $ordersJs = file_get_contents(__DIR__ . '/../../orders/assets/js/orders.js');

        // Check leads.js
        Assert::true(str_contains($leadsJs, "AppSync.register"), 'leads.js must register with AppSync');
        Assert::true(str_contains($leadsJs, "AppSync.sync('leads-list'"), 'leads.js must trigger AppSync.sync on save');
        Assert::false(str_contains($leadsJs, "location.reload(); // Reload to refresh"), 'leads.js must not contain raw reload comment');

        // Check orders.js
        Assert::true(str_contains($ordersJs, "AppSync.register"), 'orders.js must register with AppSync');
        Assert::true(str_contains($ordersJs, "AppSync.sync('orders-list'"), 'orders.js must trigger AppSync.sync on transfer');
    });

    TestRunner::test('API endpoints validate CSRF and use ApiResponse standardization', function() {
        $saveLeadCode = file_get_contents(__DIR__ . '/../../orders/api/save_lead.php');
        $transferOrderCode = file_get_contents(__DIR__ . '/../../orders/api/transfer_order.php');
        $updateStatusCode = file_get_contents(__DIR__ . '/../../orders/api/update_order_status.php');
        $bulkUpdateCode = file_get_contents(__DIR__ . '/../../labels/api/bulk_update.php');

        Assert::true(str_contains($saveLeadCode, 'ApiResponse::requireCsrf()'), 'save_lead.php must use ApiResponse::requireCsrf()');
        Assert::true(str_contains($saveLeadCode, 'ApiResponse::success('), 'save_lead.php must use ApiResponse::success()');

        Assert::true(str_contains($transferOrderCode, 'ApiResponse::requireCsrf()'), 'transfer_order.php must use ApiResponse::requireCsrf()');
        Assert::true(str_contains($transferOrderCode, 'ApiResponse::success('), 'transfer_order.php must use ApiResponse::success()');

        Assert::true(str_contains($updateStatusCode, 'ApiResponse::requireCsrf()'), 'update_order_status.php must use ApiResponse::requireCsrf()');
        Assert::true(str_contains($updateStatusCode, 'ApiResponse::success('), 'update_order_status.php must use ApiResponse::success()');

        Assert::true(str_contains($bulkUpdateCode, 'ApiResponse::requireCsrf('), 'bulk_update.php must use ApiResponse::requireCsrf()');
        Assert::true(str_contains($bulkUpdateCode, 'ApiResponse::success('), 'bulk_update.php must use ApiResponse::success()');
    });
});
