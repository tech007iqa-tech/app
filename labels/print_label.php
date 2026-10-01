<?php
/**
 * labels/print_label.php
 * Universal 2" x 1" Thermal Label Direct Browser Printer
 *
 * Calibrated specifically for thermal label rolls (2in wide x 1in high).
 * Supports Label A (Branding), Label B (Specs), or Dual-Sticker print batches.
 * Works seamlessly in Chrome, Edge, Safari, Firefox on any OS with ZERO external software.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hardware_mapping.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'both'; // 'both', 'a', 'b'
$qty = max(1, min(100, (int)($_GET['qty'] ?? 1)));
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] == '1';

$item = null;
if ($id > 0) {
    try {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

if (!$item) {
    die("<!DOCTYPE html><html><body style='font-family:sans-serif; text-align:center; padding:40px;'><h2>Hardware Item Not Found</h2><p>Please check the Item ID.</p><button onclick='window.close()'>Close</button></body></html>");
}

$F = HW_FIELDS;
$brand = htmlspecialchars($item[$F['BRAND']] ?? '', ENT_QUOTES, 'UTF-8');
$model = htmlspecialchars($item[$F['MODEL']] ?? '', ENT_QUOTES, 'UTF-8');
$series = htmlspecialchars($item[$F['SERIES']] ?? '', ENT_QUOTES, 'UTF-8');
$cpu_gen = htmlspecialchars($item[$F['CPU_GEN']] ?? '', ENT_QUOTES, 'UTF-8');
$cpu_specs = htmlspecialchars($item[$F['CPU_SPECS']] ?? '', ENT_QUOTES, 'UTF-8');
$cpu_cores = htmlspecialchars($item[$F['CPU_CORES']] ?? '', ENT_QUOTES, 'UTF-8');
$cpu_speed = htmlspecialchars($item[$F['CPU_SPEED']] ?? '', ENT_QUOTES, 'UTF-8');
$ram = htmlspecialchars($item[$F['RAM']] ?? 'None', ENT_QUOTES, 'UTF-8');
$storage = htmlspecialchars($item[$F['STORAGE']] ?? 'None', ENT_QUOTES, 'UTF-8');
$location = htmlspecialchars($item[$F['LOCATION']] ?? 'Unassigned', ENT_QUOTES, 'UTF-8');
$condition = htmlspecialchars($item[$F['DESCRIPTION']] ?? 'Untested', ENT_QUOTES, 'UTF-8');
$sn = htmlspecialchars($item[$F['SERIAL_NUMBER']] ?? '', ENT_QUOTES, 'UTF-8');
$b_val = $item[$F['BATTERY']] ?? null;
$battery = ($b_val === null || $b_val === '') ? 'N/A' : ((int)$b_val === 1 ? 'YES' : 'NO');
$gpu = htmlspecialchars($item[$F['GPU']] ?? 'Integrated', ENT_QUOTES, 'UTF-8');
$os = htmlspecialchars($item[$F['OS_VERSION']] ?? '—', ENT_QUOTES, 'UTF-8');
$bios = htmlspecialchars($item[$F['BIOS_STATE']] ?? 'Unknown', ENT_QUOTES, 'UTF-8');

$cpu_detail = trim(implode(' @ ', array_filter([$cpu_cores, $cpu_speed])));
$cpu_full = trim($cpu_specs . ($cpu_detail ? ' (' . $cpu_detail . ')' : ''));
if (!$cpu_full) $cpu_full = 'Processor N/A';

$display_sn = $sn ?: ('ID#' . str_pad($id, 5, '0', STR_PAD_LEFT));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Label #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?> (2x1 Thermal)</title>
    <style>
        /* RESET & SCREEN SETUP */
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

        .btn:hover {
            background: #475569;
        }

        .btn.active {
            background: #3b82f6;
            border-color: #60a5fa;
            color: #fff;
        }

        .btn-print {
            background: #10b981;
            border-color: #059669;
            color: #fff;
            padding: 8px 20px;
            font-size: 0.95rem;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-print:hover {
            background: #059669;
        }

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

        /* ── LABEL A: BRANDING STICKER ── */
        .label-a .brand-model-row {
            text-align: center;
            font-size: 10.5pt;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: uppercase;
        }

        .label-a .series-row {
            text-align: center;
            font-size: 8pt;
            font-weight: 700;
            color: #111;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-a .cpu-row {
            text-align: center;
            font-size: 7.5pt;
            font-weight: 600;
            color: #222;
            line-height: 1.1;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-a .barcode-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: auto;
            margin-bottom: 1px;
        }

        .barcode-bars {
            height: 16px;
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

        .label-a .footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 6pt;
            font-weight: 800;
            border-top: 0.5px solid #000;
            padding-top: 1px;
            line-height: 1;
        }

        /* ── LABEL B: TECHNICAL SPECS STICKER ── */
        .label-b {
            padding: 0.04in 0.05in;
        }

        .label-b .specs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 0.8px solid #000;
            padding-bottom: 1px;
            margin-bottom: 2px;
            line-height: 1;
        }

        .label-b .specs-title {
            font-size: 7.5pt;
            font-weight: 900;
            text-transform: uppercase;
        }

        .label-b .specs-id {
            font-size: 6.5pt;
            font-weight: 800;
            font-family: monospace;
        }

        .label-b .spec-line {
            font-size: 6.5pt;
            line-height: 1.18;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #000;
        }

        .label-b .spec-line strong {
            font-weight: 900;
        }

        .label-b .specs-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 0.5px solid #000;
            padding-top: 1px;
            margin-top: auto;
            font-size: 6pt;
            font-weight: 800;
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
            <span>🖨️ <strong>Thermal Label (2" x 1")</strong></span>
            <span>·</span>
            <span>#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?> <?= $brand ?> <?= $model ?></span>
        </div>

        <div class="toolbar-controls">
            <label style="font-size:0.8rem; color:#94a3b8;">Qty:</label>
            <input type="number" id="qtySelector" class="qty-input" value="<?= $qty ?>" min="1" max="100">

            <button type="button" class="btn <?= $mode === 'both' ? 'active' : '' ?>" onclick="switchMode('both')">📑 Both Stickers</button>
            <button type="button" class="btn <?= $mode === 'a' ? 'active' : '' ?>" onclick="switchMode('a')">🏷️ Sticker A (Brand)</button>
            <button type="button" class="btn <?= $mode === 'b' ? 'active' : '' ?>" onclick="switchMode('b')">📜 Sticker B (Specs)</button>

            <button type="button" class="btn btn-print" onclick="window.print()">
                <span>🖨️ Print Now</span>
            </button>

            <button type="button" class="btn" onclick="window.close()" title="Close Tab">✕</button>
        </div>
    </header>

    <div class="preview-hint" style="margin-top: 15px;">
        💡 <em>Thermal roll preview: 2 inches wide by 1 inch high. Set your printer margins to "None" for 100% borderless alignment.</em>
    </div>

    <!-- PRINT STAGE -->
    <main class="preview-stage" id="printContainer">
        <?php for ($i = 0; $i < $qty; $i++): ?>

            <?php if ($mode === 'both' || $mode === 'a'): ?>
            <!-- 🏷️ STICKER A: BRANDING -->
            <div class="thermal-label-page label-a">
                <div>
                    <div class="brand-model-row"><?= $brand ?> <?= $model ?></div>
                    <?php if ($series): ?>
                        <div class="series-row"><?= $series ?></div>
                    <?php endif; ?>
                    <div class="cpu-row"><?= $cpu_full ?></div>
                </div>

                <div class="barcode-section">
                    <!-- Clean SVG Vector Barcode for Thermal Print Heads -->
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
                    <div class="barcode-caption"><?= $display_sn ?></div>
                </div>

                <div class="footer-row">
                    <span>#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></span>
                    <span>LOC: <?= $location ?></span>
                    <span><?= strtoupper($condition) ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($mode === 'both' || $mode === 'b'): ?>
            <!-- 📜 STICKER B: TECHNICAL SPECS -->
            <div class="thermal-label-page label-b">
                <div class="specs-header">
                    <span class="specs-title"><?= $brand ?> <?= $model ?> <?= $series ? '(' . $series . ')' : '' ?></span>
                    <span class="specs-id">#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></span>
                </div>

                <div class="spec-line">
                    <strong>CPU:</strong> <?= $cpu_full ?>
                </div>

                <div class="spec-line">
                    <strong>RAM:</strong> <?= $ram ?> &nbsp;|&nbsp; <strong>SSD:</strong> <?= $storage ?> &nbsp;|&nbsp; <strong>BAT:</strong> <?= $battery ?>
                </div>

                <div class="spec-line">
                    <strong>GPU:</strong> <?= $gpu ?> &nbsp;|&nbsp; <strong>OS:</strong> <?= $os ?>
                </div>

                <div class="specs-footer">
                    <span>BIOS: <strong><?= strtoupper($bios) ?></strong></span>
                    <span class="loc-badge">📍 <?= $location ?></span>
                    <span><?= strtoupper($condition) ?></span>
                </div>
            </div>
            <?php endif; ?>

        <?php endfor; ?>
    </main>

    <script>
        function switchMode(newMode) {
            const url = new URL(window.location.href);
            url.searchParams.set('mode', newMode);
            const qty = document.getElementById('qtySelector').value;
            url.searchParams.set('qty', qty);
            window.location.href = url.toString();
        }

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
