<?php
/**
 * Schema Blueprint & Migration Regression Tests
 */

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Schema.php';

TestRunner::suite('Schema Integrity & Migration Regression', function() {

    TestRunner::test('Schema::repairAll runs across all databases with 0 errors', function() {
        $results = Schema::repairAll();
        Assert::true(is_array($results), 'Results should be an array of reports');
        Assert::true(isset($results['fixed']) && isset($results['errors']), 'Report must have fixed and errors keys');
        Assert::count(0, $results['errors'], 'Schema::repairAll must finish with 0 errors');
        Assert::true(count($results['fixed']) >= 30, 'Schema::repairAll must verify at least 30 system tables');
    });

    TestRunner::test('Orders database contains orders and items tables', function() {
        $pdo = Database::orders();
        Assert::tableExists($pdo, 'orders');
        Assert::tableExists($pdo, 'items');
    });

    TestRunner::test('Customers database contains customers and interaction_logs tables', function() {
        $pdo = Database::customers();
        Assert::tableExists($pdo, 'customers');
        Assert::tableExists($pdo, 'interaction_logs');
    });

    TestRunner::test('Warehouse database contains core inventory and location tables', function() {
        $pdo = Database::warehouse();
        $expected = [
            'sectors', 'inventory', 'locations', 'location_statuses',
            'working_zones', 'pricing_rules', 'location_photos'
        ];
        foreach ($expected as $tbl) {
            Assert::tableExists($pdo, $tbl, "Table [{$tbl}] must exist in warehouse database");
        }
    });

    TestRunner::test('Users database contains users and audit_log tables', function() {
        $pdo = Database::users();
        Assert::tableExists($pdo, 'users');
        Assert::tableExists($pdo, 'audit_log');
    });

    TestRunner::test('Calendar and Tech databases contain expected operational tables', function() {
        $pdoCal = Database::calendar();
        Assert::tableExists($pdoCal, 'events');

        $pdoTech = Database::tech();
        Assert::tableExists($pdoTech, 'logs');
    });

    TestRunner::test('Labels, Marketing, and Intake databases contain expected tables', function() {
        $pdoLabels = Database::labels();
        Assert::tableExists($pdoLabels, 'items');

        $pdoMkt = Database::marketing();
        Assert::tableExists($pdoMkt, 'leads');
        Assert::tableExists($pdoMkt, 'campaigns');
        Assert::tableExists($pdoMkt, 'photos');
        Assert::tableExists($pdoMkt, 'audit_logs');

        $pdoIntake = Database::intake();
        Assert::tableExists($pdoIntake, 'committed_intakes');
    });

    TestRunner::test('Migrated and non-obvious table columns are confirmed present', function() {
        $pdoCust = Database::customers();
        Assert::columnExists($pdoCust, 'customers', 'website');
        Assert::columnExists($pdoCust, 'customers', 'contact_person');
        Assert::columnExists($pdoCust, 'customers', 'account_status');
        Assert::columnExists($pdoCust, 'customers', 'lead_source');

        $pdoOrders = Database::orders();
        Assert::columnExists($pdoOrders, 'items', 'ram');
        Assert::columnExists($pdoOrders, 'items', 'storage');
        Assert::columnExists($pdoOrders, 'items', 'battery');
        Assert::columnExists($pdoOrders, 'items', 'notes');

        $pdoWarehouse = Database::warehouse();
        Assert::columnExists($pdoWarehouse, 'locations', 'status');
        Assert::columnExists($pdoWarehouse, 'locations', 'working_zone_name');

        $pdoUsers = Database::users();
        Assert::columnExists($pdoUsers, 'users', 'ppp_sequence_key');
        Assert::columnExists($pdoUsers, 'users', 'ppp_row_index');

        $pdoMkt = Database::marketing();
        Assert::columnExists($pdoMkt, 'leads', 'status');
        Assert::columnExists($pdoMkt, 'leads', 'notes');
    });
});
