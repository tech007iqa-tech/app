<?php
namespace Src;

use PDO;
use Exception;

class DbHandler
{
    private PDO $pdo;

    public function __construct()
    {
        $dbDir = dirname(__DIR__) . '/sample_data';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        $dbPath = $dbDir . '/intake.sqlite';
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS committed_intakes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT,
            qty INTEGER,
            item TEXT,
            serial TEXT,
            location TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }

    public function insertRows(array $rows): bool
    {
        if (empty($rows)) {
            return true;
        }

        $sql = "INSERT INTO committed_intakes (date, qty, item, serial, location, notes)
                VALUES (:date, :qty, :item, :serial, :location, :notes)";
        $stmt = $this->pdo->prepare($sql);

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $date = $row['Date'] ?? $row['date'] ?? date('Y-m-d');
                $qty = $row['QTY'] ?? $row['qty'] ?? 1;
                $item = $row['Item'] ?? $row['item'] ?? '';
                $serial = $row['Serial'] ?? $row['serial'] ?? '';
                $location = $row['Location'] ?? $row['location'] ?? '';
                $notes = $row['Notes'] ?? $row['notes'] ?? '';

                $stmt->execute([
                    ':date' => $date,
                    ':qty' => intval($qty),
                    ':item' => $item,
                    ':serial' => $serial,
                    ':location' => $location,
                    ':notes' => $notes
                ]);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function fetchAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM committed_intakes ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function clearAll(): bool
    {
        return $this->pdo->exec("DELETE FROM committed_intakes") !== false;
    }
}
