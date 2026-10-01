<?php
// labels/new_label.php
// Rapid Hardware Intake & High-Fidelity 2" x 1" Label Generation
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// Fetch recent 5 labels for quick cloning
$recentLabels = [];
try {
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 5");
    $recentLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="panel flex-between" style="margin-bottom: 24px; padding: 20px 28px;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight: 900; margin-bottom: 4px;">🏷️ Hardware Intake &amp; Label Creation</h1>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">
            Rapid 30-second intake. Pick a preset or enter hardware details to generate instant 2" × 1" thermal labels.
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="labels.php" class="btn btn-secondary">📦 View Inventory</a>
    </div>
</div>

<div class="intake-layout-grid">

    <!-- LEFT COLUMN: INTAKE FORM -->
    <div class="intake-form-column">
        <form id="newLabelForm" autocomplete="off">
            <?= UI::csrf_field() ?>

            <?php
                $formType = 'add';
                include __DIR__ . '/includes/hardware_form.php';
            ?>

            <!-- STICKY ACTION TOOLBAR -->
            <div class="intake-action-bar">
                <button type="submit" class="btn btn-success btn-large" id="btnSubmitThermalPrint" data-action="print" title="Save specifications and print 2x1 thermal label">
                    <span>🖨️ Print Thermal Label</span>
                </button>

                <button type="submit" class="btn btn-dark btn-large" id="btnSubmitODT" data-action="odt" title="Generate LibreOffice / Windows ODT file">
                    <span>📄 Save &amp; Windows ODT</span>
                </button>

                <button type="button" class="btn btn-secondary btn-large" id="btnResetForm" title="Clear form fields">
                    <span>✨ Start Fresh</span>
                </button>

                <button type="submit" class="btn btn-brand btn-large" id="btnSubmitSaveOnly" data-action="save" title="Save hardware record to warehouse database without printing">
                    <span>💾 Save</span>
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT COLUMN: LIVE THERMAL PREVIEW & CLONE SHELF -->
    <aside class="intake-preview-column">

        <!-- 1. LIVE 2" x 1" THERMAL STICKER PREVIEW -->
        <div class="live-sticker-preview-box">
            <div class="preview-box-header">
                <div class="preview-box-title">
                    <span style="color:var(--accent-color);">●</span> Live Thermal Sticker (2" × 1")
                </div>
                <div class="preview-tab-pills">
                    <button type="button" class="preview-tab-pill active" id="tabPrevBoth" onclick="switchPreviewTab('both')">Dual</button>
                    <button type="button" class="preview-tab-pill" id="tabPrevA" onclick="switchPreviewTab('a')">Brand</button>
                    <button type="button" class="preview-tab-pill" id="tabPrevB" onclick="switchPreviewTab('b')">Specs</button>
                </div>
            </div>

            <!-- PHYSICAL MOCKUP CONTAINER -->
            <div id="mockupStage" style="display:flex; flex-direction:column; gap:12px;">

                <!-- STICKER A: BRAND MOCKUP -->
                <div class="thermal-sticker-mockup" id="mockupLabelA">
                    <div class="mockup-branding">
                        <div class="mockup-brand-model" id="prevBrandModel">BRAND MODEL</div>
                        <div class="mockup-series" id="prevSeries">Series Unknown</div>
                        <div class="mockup-cpu" id="prevCpu">Processor N/A</div>
                    </div>

                    <div class="mockup-barcode">
                        <svg viewBox="0 0 100 20" preserveAspectRatio="none">
                            <rect x="0" y="0" width="2" height="20" fill="#000"/>
                            <rect x="4" y="0" width="1" height="20" fill="#000"/>
                            <rect x="7" y="0" width="3" height="20" fill="#000"/>
                            <rect x="12" y="0" width="2" height="20" fill="#000"/>
                            <rect x="16" y="0" width="4" height="20" fill="#000"/>
                            <rect x="22" y="0" width="1" height="20" fill="#000"/>
                            <rect x="25" y="0" width="3" height="20" fill="#000"/>
                            <rect x="30" y="0" width="2" height="20" fill="#000"/>
                            <rect x="34" y="0" width="1" height="20" fill="#000"/>
                            <rect x="37" y="0" width="4" height="20" fill="#000"/>
                            <rect x="43" y="0" width="2" height="20" fill="#000"/>
                            <rect x="47" y="0" width="1" height="20" fill="#000"/>
                            <rect x="50" y="0" width="3" height="20" fill="#000"/>
                            <rect x="55" y="0" width="2" height="20" fill="#000"/>
                            <rect x="59" y="0" width="3" height="20" fill="#000"/>
                            <rect x="64" y="0" width="1" height="20" fill="#000"/>
                            <rect x="67" y="0" width="4" height="20" fill="#000"/>
                            <rect x="73" y="0" width="2" height="20" fill="#000"/>
                            <rect x="77" y="0" width="1" height="20" fill="#000"/>
                            <rect x="80" y="0" width="3" height="20" fill="#000"/>
                            <rect x="85" y="0" width="2" height="20" fill="#000"/>
                            <rect x="89" y="0" width="1" height="20" fill="#000"/>
                            <rect x="92" y="0" width="3" height="20" fill="#000"/>
                            <rect x="97" y="0" width="2" height="20" fill="#000"/>
                        </svg>
                        <div class="mockup-sn" id="prevSN">S/N: XXXXXX</div>
                    </div>

                    <div class="mockup-footer">
                        <span>#00000</span>
                        <span id="prevLoc">LOC: —</span>
                        <span id="prevCond">UNTESTED</span>
                    </div>
                </div>

                <!-- STICKER B: SPECS MOCKUP -->
                <div class="thermal-sticker-mockup" id="mockupLabelB">
                    <div style="display:flex; justify-content:space-between; border-bottom:1px solid #000; padding-bottom:1px; margin-bottom:2px; font-size:0.65rem; font-weight:800;">
                        <span id="prevSpecsHeaderBrand">HARDWARE SPECS</span>
                        <span>#00000</span>
                    </div>

                    <div class="mockup-specs-grid">
                        <div class="mockup-spec-line">
                            <strong>CPU:</strong> <span id="prevSpecsCpu">Processor N/A</span>
                        </div>
                        <div class="mockup-spec-line">
                            <strong>RAM:</strong> <span id="prevSpecsRam">16 GB</span> &nbsp;|&nbsp; 
                            <strong>SSD:</strong> <span id="prevSpecsStorage">256 GB</span> &nbsp;|&nbsp;
                            <strong>BAT:</strong> <span id="prevSpecsBatt">YES</span>
                        </div>
                        <div class="mockup-spec-line">
                            <strong>GPU:</strong> <span id="prevSpecsGpu">Integrated</span> &nbsp;|&nbsp;
                            <strong>OS:</strong> <span id="prevSpecsOs">Win 11</span>
                        </div>
                    </div>

                    <div class="mockup-footer">
                        <span>BIOS: <strong id="prevSpecsBios">UNKNOWN</strong></span>
                        <span id="prevSpecsLoc" style="background:#000; color:#fff; padding:1px 3px; border-radius:2px;">📍 LOC: —</span>
                        <span id="prevSpecsCond">UNTESTED</span>
                    </div>
                </div>

            </div>

            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 10px; text-align: center;">
                Physical 2.0" × 1.0" thermal roll preview. Margin: 0mm.
            </div>
        </div>

        <!-- 2. RECENT INTAKES CLONE SHELF -->
        <div class="panel" style="margin-top: 20px; padding: 18px;">
            <div class="flex-between" style="margin-bottom: 12px;">
                <h4 style="font-size: 0.9rem; font-weight: 800; color: var(--text-secondary);">
                    📋 Clone Recent Specs
                </h4>
                <span style="font-size: 0.7rem; color: var(--text-muted);">Click to load</span>
            </div>

            <?php if (empty($recentLabels)): ?>
                <p style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">No recent intakes logged yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($recentLabels as $rl): 
                        $brandModel = htmlspecialchars(($rl['brand'] ?? '') . ' ' . ($rl['model'] ?? ''));
                        $cpuRam = htmlspecialchars(($rl['cpu_specs'] ?: ($rl['cpu_gen'] ?: '—')) . ' · ' . ($rl['ram'] ?: 'No RAM'));
                    ?>
                        <div class="clone-recent-card"
                             data-brand="<?= htmlspecialchars($rl['brand'] ?? '') ?>"
                             data-model="<?= htmlspecialchars($rl['model'] ?? '') ?>"
                             data-series="<?= htmlspecialchars($rl['series'] ?? '') ?>"
                             data-cpu-gen="<?= htmlspecialchars($rl['cpu_gen'] ?? '') ?>"
                             data-cpu-specs="<?= htmlspecialchars($rl['cpu_specs'] ?? '') ?>"
                             data-cpu-cores="<?= htmlspecialchars($rl['cpu_cores'] ?? '') ?>"
                             data-cpu-speed="<?= htmlspecialchars($rl['cpu_speed'] ?? '') ?>"
                             data-ram="<?= htmlspecialchars($rl['ram'] ?? '') ?>"
                             data-storage="<?= htmlspecialchars($rl['storage'] ?? '') ?>"
                             data-location="<?= htmlspecialchars($rl['warehouse_location'] ?? '') ?>"
                             title="Click to clone these specifications">
                            <div style="font-weight: 800; font-size: 0.85rem; color: var(--text-main);"><?= $brandModel ?></div>
                            <div style="font-size: 0.72rem; color: var(--text-secondary);"><?= $cpuRam ?></div>
                            <div class="flex-between" style="margin-top: 4px; padding-top: 4px; border-top: 1px dashed var(--border-color); font-size: 0.68rem;">
                                <span style="color: var(--accent-color); font-weight: bold;">#<?= str_pad($rl['id'], 5, '0', STR_PAD_LEFT) ?></span>
                                <span style="font-weight: 800; color: var(--brand-blue);">📋 CLONE</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

</div>

<style>
.intake-layout-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}

.intake-action-bar {
    position: sticky;
    bottom: 16px;
    z-index: 100;
    background: var(--bg-panel);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    padding: 16px 20px;
    box-shadow: var(--shadow-modal);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    backdrop-filter: blur(8px);
}

.clone-recent-card {
    background: var(--bg-surface-2);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-sm);
    padding: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.clone-recent-card:hover {
    background: var(--bg-panel);
    border-color: var(--accent-color);
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}

@media (max-width: 1024px) {
    .intake-layout-grid {
        grid-template-columns: 1fr;
    }

    .intake-preview-column {
        order: -1;
        margin-bottom: 20px;
    }

    .live-sticker-preview-box {
        position: static;
    }
}
</style>

<!-- Modular Form Controller -->
<script src="assets/js/forms.js?v=<?= filemtime(__DIR__ . '/assets/js/forms.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
