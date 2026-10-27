<?php
/**
 * Storage & Donor Subsystem Unit & Integration Tests
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Schema.php';

TestRunner::suite('Tech: Storage & Donor Subsystem', function() {

    TestRunner::test('storage_units table exists with required schema columns', function() {
        $pdo = Database::tech();
        Assert::tableExists($pdo, 'storage_units');

        $cols = array_column(
            $pdo->query("PRAGMA table_info(storage_units)")->fetchAll(PDO::FETCH_ASSOC),
            'name'
        );

        $expected = ['id', 'sku', 'date_added', 'brand', 'model_num', 'series', 'battery', 'screen', 'issue', 'need', 'notes', 'status', 'tech_id', 'created_at'];
        foreach ($expected as $c) {
            Assert::true(in_array($c, $cols), "Column [{$c}] must exist in storage_units table");
        }
    });

    TestRunner::test('get_storage API endpoint returns valid JSON structure and stats', function() {
        $_GET = ['status' => 'In Storage'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include __DIR__ . '/../../tech/api/get_storage.php';
        $output = ob_get_clean();

        $json = json_decode($output, true);
        Assert::true(is_array($json), 'Response should be a valid JSON object');
        Assert::true(isset($json['success']) && $json['success'] === true, 'Response success must be true');
        Assert::true(isset($json['stats']) && is_array($json['stats']), 'Response must include stats object');
        Assert::true(isset($json['data']) && is_array($json['data']), 'Response must include data array');
        Assert::true(count($json['data']) > 0, 'Data array should contain migrated storage records');
    });

    TestRunner::test('Donor matching engine correctly identifies matching donor components', function() {
        // Search for known model from seed data: 7420 with good screen
        $_GET = ['donor_model' => '7420', 'donor_part' => 'screen'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include __DIR__ . '/../../tech/api/get_storage.php';
        $output = ob_get_clean();

        $json = json_decode($output, true);
        Assert::true($json['success'], 'Donor query should succeed');
        Assert::true($json['count'] >= 1, 'Should find at least 1 donor unit for 7420 with a good screen');

        $first = $json['data'][0];
        Assert::true(stripos($first['model_num'], '7420') !== false, 'Matched unit should correspond to 7420');
        Assert::true($first['screen'] === '✔' || stripos($first['screen'], 'good') !== false, 'Matched unit must have good screen');
    });

    TestRunner::test('Storage lifecycle: Add, Harvest, and Promote to Good', function() {
        $pdo = Database::tech();
        $testSku = 'TEST-UNIT-' . time();

        // 1. Add
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'sku' => $testSku,
            'brand' => 'Dell',
            'model_num' => 'Latitude 5410',
            'series' => 'Latitude',
            'battery' => 'Good',
            'screen' => 'Cracked / Bad',
            'issue' => 'Cracked screen from shipment',
            'need' => 'Screen',
            'notes' => 'Test lifecycle unit'
        ];
        ob_start();
        include __DIR__ . '/../../tech/api/add_storage.php';
        $addOut = ob_get_clean();
        $addJson = json_decode($addOut, true);
        Assert::true($addJson['success'], 'Adding storage unit should succeed');
        $id = $addJson['id'];

        // 2. Harvest
        $_POST = [
            'id' => $id,
            'action' => 'harvest',
            'part' => 'Battery',
            'recipient' => 'Order #ORD-TEST'
        ];
        ob_start();
        include __DIR__ . '/../../tech/api/update_storage.php';
        $hOut = ob_get_clean();
        $hJson = json_decode($hOut, true);
        Assert::true($hJson['success'], 'Harvesting part should succeed');

        $chkHarvest = $pdo->query("SELECT battery FROM storage_units WHERE id = $id")->fetchColumn();
        Assert::true(stripos($chkHarvest, 'Harvested') !== false, 'Battery should be marked Harvested');

        // 3. Promote to Good
        $_POST = [
            'id' => $id,
            'action' => 'promote_to_good',
            'resolution_notes' => 'Installed donor screen. 100% tests passed.',
            'ram' => '16 GB',
            'storage' => '256 GB NVMe'
        ];
        ob_start();
        include __DIR__ . '/../../tech/api/promote_storage.php';
        $pOut = ob_get_clean();
        $pJson = json_decode($pOut, true);
        Assert::true($pJson['success'], 'Promoting to Good should succeed');
        Assert::true(isset($pJson['log_id']) && $pJson['log_id'] > 0, 'Promote should create a log_id');
        $logId = $pJson['log_id'];

        // Verify status in DB
        $chkStatus = $pdo->query("SELECT status FROM storage_units WHERE id = $id")->fetchColumn();
        Assert::equals('Promoted to Good', $chkStatus, 'Unit status should be Promoted to Good');

        $chkLog = $pdo->query("SELECT status FROM logs WHERE id = $logId")->fetchColumn();
        Assert::equals('Good', $chkLog, 'Hardware log status should be Good');

        // Cleanup
        $pdo->prepare("DELETE FROM storage_units WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM logs WHERE id = ?")->execute([$logId]);
    });

    TestRunner::test('Barcode128 generator and SKU-only thermal label rendering', function() {
        require_once __DIR__ . '/../../tech/core/barcode.php';

        // 1. Verify vector SVG generation
        $sku = 'DL-22';
        $svg = Barcode128::getSvg($sku, 48, 2);
        Assert::true(str_starts_with($svg, '<svg'), 'Barcode output must start with <svg');
        Assert::true(str_contains($svg, '<rect'), 'Barcode output must contain black bar <rect> elements');
        Assert::true(str_ends_with($svg, '</svg>'), 'Barcode output must close with </svg>');

        // 2. Verify print_storage_label.php in SKU-only format
        $pdo = Database::tech();
        $sample = $pdo->query("SELECT id, sku FROM storage_units LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($sample) {
            $_GET = [
                'id' => $sample['id'],
                'format' => 'sku_only'
            ];
            $_SERVER['REQUEST_METHOD'] = 'GET';
            ob_start();
            include __DIR__ . '/../../tech/pages/print_storage_label.php';
            $html = ob_get_clean();

            Assert::true(str_contains($html, 'format-sku_only'), 'Rendered HTML should have format-sku_only class on body');
            Assert::true(str_contains($html, $sample['sku']), 'Rendered HTML must contain unit SKU');
            Assert::true(str_contains($html, 'sku-only-barcode'), 'Rendered HTML must contain SKU-only barcode container');
        }
    });
});
