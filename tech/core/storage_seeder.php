<?php
/**
 * Storage Seeder - Extracts historical storage and missing parts units from Technician Tasks.xlsx
 */
date_default_timezone_set('America/Los_Angeles');
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Schema.php';

$file = __DIR__ . '/../trash/Technician Tasks.xlsx';
if (!file_exists($file)) {
    echo "File not found: $file\n";
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    echo "Failed to open zip\n";
    exit(1);
}

// 1. Shared Strings
$sharedStrings = [];
$ssContent = $zip->getFromName('xl/sharedStrings.xml');
if ($ssContent) {
    $ssXml = simplexml_load_string($ssContent);
    foreach ($ssXml->si as $si) {
        if (isset($si->t)) {
            $sharedStrings[] = (string)$si->t;
        } elseif (isset($si->r)) {
            $t = '';
            foreach ($si->r as $r) {
                $t .= (string)$r->t;
            }
            $sharedStrings[] = $t;
        } else {
            $sharedStrings[] = '';
        }
    }
}

// 2. Workbook & Relationships
$wbXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
$relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));

$rels = [];
foreach ($relsXml->Relationship as $r) {
    $rels[(string)$r['Id']] = (string)$r['Target'];
}

function getCellValue($c, $sharedStrings) {
    if (!$c) return '';
    $type = (string)$c['t'];
    $val = (string)$c->v;
    if ($type === 's' && isset($sharedStrings[(int)$val])) {
        return $sharedStrings[(int)$val];
    }
    return $val;
}

function excelDateToDate($val) {
    if (is_numeric($val) && (float)$val > 30000) {
        $unix = ((float)$val - 25569) * 86400;
        return gmdate('Y-m-d', (int)$unix);
    }
    return date('Y-m-d');
}

$db = Database::tech();
Schema::ensure('tech', 'storage_units');

$stmt = $db->prepare("
    INSERT OR IGNORE INTO storage_units 
    (sku, date_added, brand, model_num, series, battery, screen, issue, need, notes, status, tech_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'In Storage', 'migrated')
");

$importedCount = 0;

foreach ($wbXml->sheets->sheet as $sheet) {
    $name = (string)$sheet['name'];
    // r:id is in the http://schemas.openxmlformats.org/officeDocument/2006/relationships namespace
    $namespaces = $sheet->getNamespaces(true);
    $rId = '';
    if (isset($namespaces['r'])) {
        $rAttr = $sheet->attributes($namespaces['r']);
        $rId = (string)$rAttr['id'];
    }
    if (empty($rId)) {
        $rId = (string)$sheet['id'];
    }
    if (!isset($rels[$rId])) continue;
    $target = $rels[$rId];
    if (strpos($target, '/') === 0) $target = substr($target, 1);
    else $target = 'xl/' . $target;

    $sheetContent = $zip->getFromName($target);
    if (!$sheetContent) continue;
    $sXml = simplexml_load_string($sheetContent);
    if (!$sXml || !isset($sXml->sheetData->row)) continue;

    $isMissingTab = ($name === 'Missing 1 Part');
    $brandTabs = ['Dell(XPS)', 'Dell (Precision)', 'Dell(Latitude)', 'HP(Zbook)', 'HP(EliteBook)', 'HP(ProBook)', 'Lenovo(ThinkPad)', 'Lenovo(Others)', 'Apple', 'Microsoft'];
    
    if (!$isMissingTab && !in_array($name, $brandTabs)) {
        continue;
    }

    $brandFromName = '';
    if (stripos($name, 'Dell') !== false) $brandFromName = 'Dell';
    elseif (stripos($name, 'HP') !== false) $brandFromName = 'HP';
    elseif (stripos($name, 'Lenovo') !== false) $brandFromName = 'Lenovo';
    elseif (stripos($name, 'Apple') !== false) $brandFromName = 'Apple';
    elseif (stripos($name, 'Microsoft') !== false) $brandFromName = 'Microsoft';

    $seriesFromName = '';
    if (preg_match('/\((.*?)\)/', $name, $m)) {
        $seriesFromName = $m[1];
    }

    $rows = $sXml->sheetData->row;
    $rowCount = count($rows);

    for ($i = 1; $i < $rowCount; $i++) {
        $row = $rows[$i];
        $cells = [];
        foreach ($row->c as $c) {
            $colLetter = preg_replace('/\d+/', '', (string)$c['r']);
            $cells[$colLetter] = getCellValue($c, $sharedStrings);
        }

        $sku = trim($cells['A'] ?? '');
        if (empty($sku) || $sku === 'SKU' || $sku === 'Internal SKU' || strpos($sku, '-0') !== false) {
            continue;
        }

        $dateAdded = excelDateToDate($cells['B'] ?? '');

        if ($isMissingTab) {
            $brand = trim($cells['D'] ?? $brandFromName);
            $series = trim($cells['E'] ?? $seriesFromName);
            $modelNum = trim($cells['F'] ?? '');
            $battery = trim($cells['L'] ?? '');
            $screen = trim($cells['M'] ?? '');
            $notes = trim($cells['N'] ?? '');
            $need = trim($cells['O'] ?? '');
            $issue = (!empty($need) ? "Missing $need" : "Needs Review");

            if (!empty($cells['G'])) $notes .= " | CPU: " . trim($cells['G']);
            if (!empty($cells['I'])) $notes .= " | RAM: " . trim($cells['I']);
            if (!empty($cells['J'])) $notes .= " | Storage: " . trim($cells['J']);
            $notes = trim($notes, " |");

            $stmt->execute([$sku, $dateAdded, $brand, $modelNum, $series, $battery, $screen, $issue, $need, $notes]);
            $importedCount += $stmt->rowCount();
        } else {
            // Brand tabs: Internal SKU (A) | Date Added (B) | Model # (C) [or Series (C) & Model # (D)] | Battery | Screen | Issue | Notes
            $isLenovoOthers = ($name === 'Lenovo(Others)');
            if ($isLenovoOthers) {
                $series = trim($cells['C'] ?? '');
                $modelNum = trim($cells['D'] ?? '');
                $battery = trim($cells['E'] ?? '');
                $screen = trim($cells['F'] ?? '');
                $issue = trim($cells['G'] ?? '');
                $notes = trim($cells['H'] ?? '');
            } else {
                $series = $seriesFromName;
                $modelNum = trim($cells['C'] ?? '');
                $battery = trim($cells['D'] ?? '');
                $screen = trim($cells['E'] ?? '');
                $issue = trim($cells['F'] ?? '');
                $notes = trim($cells['G'] ?? '');
            }

            // Clean up float strings like 7430.0 -> 7430
            if (is_numeric($modelNum) && floor((float)$modelNum) == (float)$modelNum) {
                $modelNum = (string)(int)$modelNum;
            }

            $need = '';
            if (stripos($issue, 'screen') !== false || stripos($notes, 'screen') !== false) {
                $need = 'Screen';
            } elseif (stripos($issue, 'bat') !== false || stripos($notes, 'bat') !== false) {
                $need = 'Battery';
            } elseif (stripos($issue, 'ssd') !== false || stripos($notes, 'ssd') !== false) {
                $need = 'SSD';
            }

            $stmt->execute([$sku, $dateAdded, $brandFromName, $modelNum, $series, $battery, $screen, $issue, $need, $notes]);
            $importedCount += $stmt->rowCount();
        }
    }
}

$zip->close();

$totalInDb = $db->query("SELECT COUNT(*) FROM storage_units")->fetchColumn();
echo "Imported: $importedCount units. Total in storage_units: $totalInDb\n";
