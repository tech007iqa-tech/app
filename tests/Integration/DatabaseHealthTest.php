<?php
/**
 * Database Health & SQLite Pragma Compliance Integration Tests
 */

require_once __DIR__ . '/../../core/Database.php';

TestRunner::suite('Database Health & SQLite Pragmas', function() {

    $databases = [
        'orders'    => fn() => Database::orders(),
        'customers' => fn() => Database::customers(),
        'warehouse' => fn() => Database::warehouse(),
        'users'     => fn() => Database::users(),
        'calendar'  => fn() => Database::calendar(),
        'tech'      => fn() => Database::tech(),
        'marketing' => fn() => Database::marketing(),
        'labels'    => fn() => Database::labels(),
        'intake'    => fn() => Database::intake(),
        'audit'     => fn() => Database::audit(),
    ];

    foreach ($databases as $name => $getter) {
        TestRunner::test("Database [{$name}] connects and maintains mandatory SQLite pragmas", function() use ($name, $getter) {
            /** @var PDO $conn */
            $conn = $getter();
            Assert::true($conn instanceof PDO, "Connection to {$name} must be a PDO instance");

            // 1. Verify WAL Mode
            $journalMode = $conn->query("PRAGMA journal_mode")->fetchColumn();
            Assert::true(
                strcasecmp($journalMode, 'wal') === 0,
                "Database [{$name}] journal_mode must be 'wal', got '{$journalMode}'"
            );

            // 2. Verify Foreign Keys Enabled
            $foreignKeys = (int)$conn->query("PRAGMA foreign_keys")->fetchColumn();
            Assert::same(1, $foreignKeys, "Database [{$name}] foreign_keys pragma must be ON (1)");

            // 3. Verify Busy Timeout >= 5000ms
            $busyTimeout = (int)$conn->query("PRAGMA busy_timeout")->fetchColumn();
            Assert::true(
                $busyTimeout >= 5000,
                "Database [{$name}] busy_timeout must be at least 5000ms, got {$busyTimeout}ms"
            );

            // 4. Verify SQLite B-Tree Quick Check Integrity
            $integrity = $conn->query("PRAGMA quick_check")->fetchColumn();
            Assert::same('ok', strtolower($integrity), "Database [{$name}] integrity quick_check must return 'ok'");
        });
    }

    TestRunner::test('Connection pool reuses existing PDO instances (Singleton per DB)', function() {
        $conn1 = Database::orders();
        $conn2 = Database::orders();
        Assert::same($conn1, $conn2, 'Subsequent Database::orders() calls must return the same connection instance');

        $users1 = Database::users();
        $users2 = Database::users();
        Assert::same($users1, $users2, 'Subsequent Database::users() calls must return the same connection instance');
    });
});
