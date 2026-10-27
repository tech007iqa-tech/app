<?php
/**
 * tech/pages/print_storage_label.php
 * Thermal Label Generator for Storage & Donor Units
 * 2.0" x 1.0" High-Contrast Barcode Sticker for Rollo / Zebra / Dymo
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/barcode.php';

$id  = (int)($_GET['id'] ?? 0);
$sku = trim($_GET['sku'] ?? '');
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

$conn = Database::tech();
$unit = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM storage_units WHERE id = ?");
    $stmt->execute([$id]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($sku)) {
    $stmt = $conn->prepare("SELECT * FROM storage_units WHERE sku = ?");
    $stmt->execute([$sku]);
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$unit) {
    die("Storage unit not found.");
}

$format = strtolower(trim($_GET['format'] ?? 'sku_only'));
if ($format !== 'detailed') {
    $format = 'sku_only';
}

$barcodeSvgSkuOnly = Barcode128::getSvg($unit['sku'], 48, 2);
$barcodeSvgDetailed = Barcode128::getSvg($unit['sku'], 26, 2);
$modelDisplay = trim($unit['brand'] . ' ' . ($unit['series'] ? $unit['series'] . ' ' : '') . $unit['model_num']);
$issueDisplay = !empty($unit['need']) ? "Need: {$unit['need']}" : $unit['issue'];
if (strlen($issueDisplay) > 28) {
    $issueDisplay = substr($issueDisplay, 0, 26) . '..';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Label - <?= htmlspecialchars($unit['sku']) ?></title>
    <style>
        /* Screen & Print Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: #000000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Screen Controls (Hidden during print) */
        .controls-bar {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }

        .btn-print {
            height: 44px;
            padding: 0 20px;
            background: #0284c7;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
        }

        .btn-print:hover {
            background: #0369a1;
        }

        .format-toggle {
            display: inline-flex;
            background: #e2e8f0;
            border-radius: 8px;
            padding: 3px;
            gap: 3px;
        }

        .btn-format {
            height: 38px;
            padding: 0 14px;
            border: none;
            background: transparent;
            color: #475569;
            font-size: 0.88rem;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-format.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-close {
            height: 44px;
            padding: 0 16px;
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-close:hover {
            background: #f8fafc;
        }

        /* Exact 2" x 1" Thermal Sticker Preview */
        .label-sheet {
            width: 2.0in;
            height: 1.0in;
            background: #ffffff;
            border: 1px dashed #94a3b8;
            padding: 0.04in 0.06in;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        /* Mode 1: SKU ONLY (High-visibility for storage shelves & barcode guns) */
        .sku-only-layout {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 2px 0;
        }

        .sku-only-text {
            font-size: 15pt;
            font-weight: 900;
            letter-spacing: 0.5px;
            line-height: 1.1;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: #000000;
            text-align: center;
        }

        .sku-only-barcode {
            width: 95%;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .sku-only-barcode svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Mode 2: DETAILED (SKU + Barcode + Model + Issue) */
        .detailed-layout {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
        }

        .label-header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 5.5pt;
            font-weight: 800;
            color: #000;
            text-transform: uppercase;
            border-bottom: 0.5pt solid #000;
            padding-bottom: 1px;
            line-height: 1;
        }

        .label-sku-box {
            margin: 1px 0;
            line-height: 1;
        }

        .label-sku {
            font-size: 12pt;
            font-weight: 900;
            letter-spacing: -0.5px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        .label-barcode {
            width: 95%;
            height: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 1px 0;
        }

        .label-barcode svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .label-footer {
            width: 100%;
            border-top: 0.5pt solid #000;
            padding-top: 1px;
            line-height: 1.1;
        }

        .label-model {
            font-size: 6pt;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-issue {
            font-size: 5.5pt;
            font-weight: 700;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Dynamic Visibility */
        .format-sku_only .sku-only-layout { display: flex; }
        .format-sku_only .detailed-layout { display: none; }

        .format-detailed .sku-only-layout { display: none; }
        .format-detailed .detailed-layout { display: flex; }

        /* ── PRINT MEDIA RULES ─────────────────────────────────────────────────── */
        @media print {
            @page {
                size: 2.0in 1.0in;
                margin: 0;
            }

            body {
                background: none;
                padding: 0;
                margin: 0;
                min-height: auto;
            }

            .controls-bar {
                display: none !important;
            }

            .label-sheet {
                border: none;
                box-shadow: none;
                width: 2.0in;
                height: 1.0in;
                page-break-after: always;
            }
        }
    </style>
</head>
<body class="format-<?= htmlspecialchars($format) ?>">

    <div class="controls-bar">
        <button type="button" class="btn-print" onclick="window.print()">
            🖨️ Print Label (2" x 1")
        </button>

        <div class="format-toggle">
            <button type="button" id="btnFormatSkuOnly" class="btn-format <?= $format === 'sku_only' ? 'active' : '' ?>" onclick="switchFormat('sku_only')">
                🏷️ SKU &amp; Barcode Only
            </button>
            <button type="button" id="btnFormatDetailed" class="btn-format <?= $format === 'detailed' ? 'active' : '' ?>" onclick="switchFormat('detailed')">
                📋 Detailed (Specs &amp; Issue)
            </button>
        </div>

        <button type="button" class="btn-close" onclick="window.close()">
            Close
        </button>
    </div>

    <!-- The 2.0" x 1.0" Thermal Sticker -->
    <div class="label-sheet" id="labelSheet">
        <!-- SKU ONLY LAYOUT (Optimal for storage racks and barcode readers) -->
        <div class="sku-only-layout">
            <div class="sku-only-text"><?= htmlspecialchars($unit['sku']) ?></div>
            <div class="sku-only-barcode">
                <?= $barcodeSvgSkuOnly ?>
            </div>
        </div>

        <!-- DETAILED LAYOUT (Includes model and triage issues) -->
        <div class="detailed-layout">
            <div class="label-header">
                <span>IQA WAREHOUSE</span>
                <span><?= htmlspecialchars($unit['date_added']) ?></span>
            </div>

            <div class="label-sku-box">
                <div class="label-sku"><?= htmlspecialchars($unit['sku']) ?></div>
            </div>

            <div class="label-barcode">
                <?= $barcodeSvgDetailed ?>
            </div>

            <div class="label-footer">
                <div class="label-model"><?= htmlspecialchars($modelDisplay) ?></div>
                <div class="label-issue"><?= htmlspecialchars($issueDisplay) ?></div>
            </div>
        </div>
    </div>

    <script>
        function switchFormat(mode) {
            document.body.classList.remove('format-sku_only', 'format-detailed');
            document.body.classList.add('format-' + mode);
            document.getElementById('btnFormatSkuOnly').classList.toggle('active', mode === 'sku_only');
            document.getElementById('btnFormatDetailed').classList.toggle('active', mode === 'detailed');

            // Update URL query string without reloading
            const url = new URL(window.location);
            url.searchParams.set('format', mode);
            window.history.replaceState({}, '', url);
        }

        <?php if ($autoprint): ?>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 350);
        });
        <?php endif; ?>
    </script>

</body>
</html>
