<?php
require_once 'includes/db.php';

$dir = 'exports/labels/';
$files = scandir($dir);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;

    // Match the old pattern: Label_[id]_[rest].odt
    if (preg_match('/^Label_(\d+)_.*\.odt$/', $file, $matches)) {
        $id = $matches[1];

        // Fetch current details from DB
        $stmt = $pdo_labels->prepare("SELECT brand, model, cpu_gen FROM items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item) {
            $brand = preg_replace('/[^a-zA-Z0-9]/', '', $item['brand']);
            $model = preg_replace('/[^a-zA-Z0-9]/', '', $item['model']);
            $gen   = preg_replace('/[^a-zA-Z0-9]/', '', $item['cpu_gen'] ?? 'Gen');

            $newName = "{$brand}_{$model}_{$gen}_ID{$id}.odt";

            if ($file !== $newName) {
                echo "Renaming $file -> $newName\n";
                rename($dir . $file, $dir . $newName);
            }
        }
    }
}
?>
