<?php
/**
 * labels/print_battery_label.php
 * Universal 2" x 1" Thermal Label Direct Browser Printer for Battery Packs
 *
 * Calibrated specifically for thermal continuous rolls (2in wide x 1in high).
 * Zero software setup. Crisp SVG barcodes and high-contrast typography.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$qty = max(1, min(100, (int)($_GET['qty'] ?? 1)));
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

$battery = null;
if ($id > 0) {
    try {
        $stmt = $pdo_labels->prepare("SELECT * FROM batteries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $battery = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

if (!$battery) {
    die("<!DOCTYPE html><html><body style='font-family:sans-serif; text-align:center; padding:40px;'><h2>Battery Item Not Found</h2><p>Please check the Battery ID.</p><button onclick='window.close()'>Close</button></body></html>");
}

$brand = htmlspecialchars($battery['brand'] ?? '', ENT_QUOTES, 'UTF-8');
$part_number = htmlspecialchars($battery['part_number'] ?? '', ENT_QUOTES, 'UTF-8');
$voltage = htmlspecialchars($battery['voltage'] ?? '', ENT_QUOTES, 'UTF-8');
$capacity_wh = htmlspecialchars($battery['capacity_wh'] ?? '', ENT_QUOTES, 'UTF-8');
$cell_count = htmlspecialchars($battery['cell_count'] ?? '', ENT_QUOTES, 'UTF-8');
$chemistry = htmlspecialchars($battery['chemistry'] ?? 'Li-ion', ENT_QUOTES, 'UTF-8');
$location = htmlspecialchars($battery['warehouse_location'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8');
$condition = htmlspecialchars($battery['condition'] ?? 'Tested OEM 80%+', ENT_QUOTES, 'UTF-8');
$models_raw = $battery['compatible_models'] ?? '';

// Build concise fits line
$models_array = array_map('trim', explode(',', $models_raw));
$fits_sample = array_slice($models_array, 0, 3);
$fits_str = implode(', ', $fits_sample);
if (count($models_array) > 3) {
    $fits_str .= ' +' . (count($models_array) - 3) . ' more';
}
$fits_display = htmlspecialchars($fits_str, ENT_QUOTES, 'UTF-8');

$specs_line = trim(implode(' · ', array_filter([$voltage, $capacity_wh, $cell_count, $chemistry])));
$display_code = $part_number ?: ('BAT#' . str_pad($id, 4, '0', STR_PAD_LEFT));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Battery Label #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?> - <?= $brand ?> <?= $part_number ?> (2x1 Thermal)</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background-color: #0f172a;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* SCREEN TOOLBAR (Hidden on print) */
        .print-toolbar {
            width: 100%;
            background: #1e293b;
            color: #f8fafc;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .toolbar-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .toolbar-info strong {
            color: #10b981;
        }

        .toolbar-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            background: #334155;
            color: #f8fafc;
            border: 1px solid #475569;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover { background: #475569; }

        .btn-print {
            background: #10b981;
            border-color: #059669;
            color: #fff;
            padding: 8px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-print:hover { background: #059669; }

        .qty-input {
            width: 55px;
            padding: 6px;
            border-radius: 6px;
            border: 1px solid #475569;
            background: #0f172a;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        /* PREVIEW STAGE */
        .preview-stage {
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .preview-hint {
            color: #94a3b8;
            font-size: 0.85rem;
            text-align: center;
        }

        /* ── EXACT 2" x 1" THERMAL STICKER SPEC ── */
        .thermal-label-page {
            width: 2in;
            height: 1in;
            background: #ffffff;
            color: #000000;
            padding: 0.05in 0.06in 0.03in 0.06in;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border-radius: 2px;
            page-break-after: always;
            break-after: page;
        }

        .battery-header-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 1px solid #000;
            padding-bottom: 1px;
            line-height: 1;
        }

        .battery-brand {
            font-size: 7pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .battery-part-num {
            font-size: 11pt;
            font-weight: 900;
            letter-spacing: -0.2px;
            text-transform: uppercase;
            line-height: 1;
        }

        .battery-specs-row {
            text-align: center;
            font-size: 7pt;
            font-weight: 800;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .battery-fits-row {
            font-size: 6pt;
            font-weight: 700;
            color: #111;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
            margin-top: 1px;
        }

        .barcode-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: auto;
            margin-bottom: 1px;
        }

        .barcode-bars {
            height: 15px;
            width: 1.45in;
        }

        .barcode-caption {
            font-family: "Courier New", Courier, monospace;
            font-size: 6.5pt;
            font-weight: 700;
            letter-spacing: 0.5px;
            line-height: 1;
            margin-top: 1px;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 5.8pt;
            font-weight: 800;
            border-top: 0.5px solid #000;
            padding-top: 1px;
            line-height: 1;
        }

        .loc-badge {
            background: #000;
            color: #fff;
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 5.5pt;
        }

        /* ── PURE BROWSER PRINT ENGINE ── */
        @media print {
            body {
                background: transparent !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-toolbar, .preview-hint {
                display: none !important;
            }

            .preview-stage {
                padding: 0 !important;
                gap: 0 !important;
            }

            .thermal-label-page {
                box-shadow: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                page-break-after: always !important;
                break-after: page !important;
            }

            @page {
                size: 2in 1in;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY CONTROL TOOLBAR -->
    <header class="print-toolbar">
        <div class="toolbar-info">
            <span>🔋 <strong>Battery Label (2" x 1")</strong></span>
            <span>·</span>
            <span>#<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?> <?= $brand ?> <?= $part_number ?> (<?= $capacity_wh ?>)</span>
        </div>

        <div class="toolbar-controls">
            <label style="font-size:0.8rem; color:#94a3b8;">Qty:</label>
            <input type="number" id="qtySelector" class="qty-input" value="<?= $qty ?>" min="1" max="100">

            <button type="button" class="btn btn-print" onclick="window.print()">
                <span>🖨️ Print Now</span>
            </button>

            <button type="button" class="btn" onclick="window.close()" title="Close Tab">✕</button>
        </div>
    </header>

    <div class="preview-hint" style="margin-top: 15px;">
        💡 <em>Battery thermal roll preview: 2 inches wide by 1 inch high. Set margins to "None" in printer dialog for borderless alignment.</em>
    </div>

    <!-- PRINT STAGE -->
    <main class="preview-stage" id="printContainer">
        <?php for ($i = 0; $i < $qty; $i++): ?>
            <div class="thermal-label-page">
                <div>
                    <div class="battery-header-row">
                        <span class="battery-brand"><?= $brand ?></span>
                        <span class="battery-part-num"><?= $part_number ?></span>
                        <span style="font-size:6pt; font-weight:800; font-family:monospace;">#BAT-<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></span>
                    </div>

                    <div class="battery-specs-row"><?= $specs_line ?></div>
                    <div class="battery-fits-row">FITS: <?= $fits_display ?></div>
                </div>

                <div class="barcode-section">
                    <svg class="barcode-bars" viewBox="0 0 100 20" preserveAspectRatio="none">
                        <rect x="0" y="0" width="2" height="20" fill="#000"/>
                        <rect x="3" y="0" width="1" height="20" fill="#000"/>
                        <rect x="6" y="0" width="3" height="20" fill="#000"/>
                        <rect x="11" y="0" width="1" height="20" fill="#000"/>
                        <rect x="14" y="0" width="2" height="20" fill="#000"/>
                        <rect x="18" y="0" width="3" height="20" fill="#000"/>
                        <rect x="23" y="0" width="1" height="20" fill="#000"/>
                        <rect x="26" y="0" width="2" height="20" fill="#000"/>
                        <rect x="30" y="0" width="4" height="20" fill="#000"/>
                        <rect x="36" y="0" width="1" height="20" fill="#000"/>
                        <rect x="39" y="0" width="2" height="20" fill="#000"/>
                        <rect x="43" y="0" width="3" height="20" fill="#000"/>
                        <rect x="48" y="0" width="1" height="20" fill="#000"/>
                        <rect x="51" y="0" width="3" height="20" fill="#000"/>
                        <rect x="56" y="0" width="2" height="20" fill="#000"/>
                        <rect x="60" y="0" width="1" height="20" fill="#000"/>
                        <rect x="63" y="0" width="4" height="20" fill="#000"/>
                        <rect x="69" y="0" width="1" height="20" fill="#000"/>
                        <rect x="72" y="0" width="2" height="20" fill="#000"/>
                        <rect x="76" y="0" width="3" height="20" fill="#000"/>
                        <rect x="81" y="0" width="2" height="20" fill="#000"/>
                        <rect x="85" y="0" width="1" height="20" fill="#000"/>
                        <rect x="88" y="0" width="3" height="20" fill="#000"/>
                        <rect x="93" y="0" width="2" height="20" fill="#000"/>
                        <rect x="97" y="0" width="3" height="20" fill="#000"/>
                    </svg>
                    <div class="barcode-caption"><?= $display_code ?></div>
                </div>

                <div class="footer-row">
                    <span class="loc-badge">BIN: <?= $location ?></span>
                    <span><?= strtoupper($condition) ?></span>
                    <span>IQA BATTERIES</span>
                </div>
            </div>
        <?php endfor; ?>
    </main>

    <script>
        document.getElementById('qtySelector').addEventListener('change', function() {
            const url = new URL(window.location.href);
            url.searchParams.set('qty', this.value);
            window.location.href = url.toString();
        });

        // Auto-print support if requested via URL
        <?php if ($autoprint): ?>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 400);
        });
        <?php endif; ?>
    </script>
</body>
</html>
