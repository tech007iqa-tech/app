<?php
// core/db.php

$databaseCandidates = [
    __DIR__ . '/../../core/Database.php',
    dirname(__DIR__, 2) . '/core/Database.php',
    __DIR__ . '/../../serverWarehouse/core/Database.php',
    dirname(__DIR__, 3) . '/serverWarehouse/core/Database.php',
    dirname(__DIR__, 3) . '/core/Database.php',
];

$databaseClass = null;
foreach ($databaseCandidates as $cand) {
    if (file_exists($cand)) {
        $databaseClass = $cand;
        break;
    }
}

if ($databaseClass) {
    require_once $databaseClass;
    $db = Database::warehouse();
} else {
    $dbCandidates = [
        dirname(__DIR__, 3) . '/data/db/warehouse.db',
        dirname(__DIR__, 2) . '/data/db/warehouse.db',
        __DIR__ . '/../../data/db/warehouse.db',
        '/home4/latinspc/data/db/warehouse.db',
    ];

    $dbPath = null;
    foreach ($dbCandidates as $cand) {
        if (file_exists($cand)) {
            $dbPath = $cand;
            break;
        }
    }

    if (!$dbPath) {
        $dbPath = dirname(__DIR__, 3) . '/data/db/warehouse.db';
    }

    try {
        $db = new PDO("sqlite:" . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("PRAGMA journal_mode = WAL;");
        $db->exec("PRAGMA busy_timeout = 5000;");
        $db->exec("PRAGMA foreign_keys = ON;");
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Self-healing check for inventory.is_posted
try {
    $cols = $db->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'name');
    if (!empty($col_names) && !in_array('is_posted', $col_names)) {
        $db->exec("ALTER TABLE inventory ADD COLUMN is_posted INTEGER DEFAULT 0");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_inv_is_posted ON inventory(is_posted)");
        $db->exec("UPDATE inventory SET is_posted = 1 WHERE user_owner = 'STORE'");
    }
} catch (Exception $e) {
}