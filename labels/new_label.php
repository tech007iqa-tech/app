<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Fetch recent labels for the sidebar
$recentLabels = [];
try {
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 5");
    $recentLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="panel mobile-header-panel">
    <h1>🏷️ Add to Warehouse</h1>
    <p>Rapid hardware intake and label generation.</p>
</div>

<div class="sidebar-layout">

    <!-- MAIN FORM COLUMN -->
    <div class="panel form-panel" style="position: relative;">
        <form id="newLabelForm">
            <?= UI::csrf_field() ?>
            <?php
                $formType = 'add';
                include 'includes/hardware_form.php';
            ?>

            <!-- DESKTOP ACTION BAR -->
            <div class="desktop-only" style="margin-top: 20px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <button type="submit" class="btn btn-success" id="submitLabelBtnDesktop" style="font-size: 1.1rem; padding: 12px 24px; font-weight: bold;">
                    ➕ Save & Print Label
                </button>
            </div>

            <!-- MOBILE STICKY ACTION BAR -->
            <div class="mobile-sticky-bar">
                <button type="submit" class="btn btn-success btn-large" id="submitLabelBtnMobile">
                    ➕ Save & Print Label
                </button>
            </div>
        </form>

        <!-- SUCCESS OVERLAY (Hidden by default) -->
        <div id="successOverlay" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.98); border-radius: 0; z-index:9999; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; backdrop-filter: blur(8px);">

            <!-- Success Animation Circle -->
            <div class="success-checkmark">
                <div class="check-icon">
                    <span class="icon-line line-tip"></span>
                    <span class="icon-line line-long"></span>
                    <div class="icon-circle"></div>
                    <div class="icon-fix"></div>
                </div>
            </div>

            <h2 style="color:var(--text-main); font-size: 1.6rem; margin-top: 10px; font-weight: 800;">Label Logged!</h2>
            <p id="successMsg" style="color:var(--text-secondary); margin-bottom:30px; font-size: 0.95rem; line-height: 1.4;"></p>

            <!-- PRIMARY ACTIONS -->
            <div style="display:flex; flex-direction: column; gap:12px; width: 100%; max-width: 320px;">
                <button id="btnQuickPrint" class="btn btn-success btn-large" style="gap: 10px; box-shadow: 0 10px 20px rgba(140, 198, 63, 0.3);">
                    🏷️ Launch Label
                </button>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button id="btnAgain" class="btn btn-primary" style="font-size: 0.85rem; padding: 0 10px;">⚙️ Print Config</button>
                    <button id="btnCloneNext" class="btn btn-primary" style="font-size: 0.85rem; padding: 0 10px; background: var(--text-secondary) !important;">📦 Add Another</button>
                </div>

                <div style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button id="btnReset" class="btn" style="background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main); font-size: 0.85rem;">✨ Start Fresh</button>
                    <a href="labels.php" class="btn" style="background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main); font-size: 0.85rem;">📦 Inventory</a>
                </div>
            </div>
        </div>

<style>
/* PREMIUM SUCCESS ANIMATION */
.success-checkmark {
    width: 80px;
    height: 115px;
    margin: 0 auto;
}
.check-icon {
    width: 80px;
    height: 80px;
    position: relative;
    border-radius: 50%;
    box-sizing: content-box;
    border: 4px solid var(--accent-color);
}
.check-icon::before {
    top: 3px;
    left: -2px;
    width: 30px;
    transform-origin: 100% 50%;
    border-radius: 100px 0 0 100px;
}
.check-icon::after {
    top: 0;
    left: 30px;
    width: 60px;
    transform-origin: 0 50%;
    border-radius: 0 100px 100px 0;
    animation: rotate-circle 4.25s ease-in;
}
.icon-line {
    height: 5px;
    background-color: var(--accent-color);
    display: block;
    border-radius: 2px;
    position: absolute;
    z-index: 10;
}
.line-tip {
    top: 46px;
    left: 14px;
    width: 25px;
    transform: rotate(45deg);
    animation: icon-line-tip 0.75s;
}
.line-long {
    top: 38px;
    right: 8px;
    width: 47px;
    transform: rotate(-45deg);
    animation: icon-line-long 0.75s;
}
.icon-circle {
    top: -4px;
    left: -4px;
    z-index: 10;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    position: absolute;
    box-sizing: content-box;
    border: 4px solid rgba(140, 198, 63, 0.2);
}
.icon-fix {
    top: 8px;
    width: 5px;
    left: 26px;
    z-index: 1;
    height: 85px;
    position: absolute;
    transform: rotate(-45deg);
    background-color: transparent;
}

@keyframes icon-line-tip {
    0% { width: 0; left: 1px; top: 19px; }
    54% { width: 0; left: 1px; top: 19px; }
    70% { width: 50px; left: -8px; top: 37px; }
    84% { width: 17px; left: 21px; top: 48px; }
    100% { width: 25px; left: 14px; top: 46px; }
}
@keyframes icon-line-long {
    0% { width: 0; right: 46px; top: 54px; }
    65% { width: 0; right: 46px; top: 54px; }
    84% { width: 55px; right: 0px; top: 35px; }
    100% { width: 47px; right: 8px; top: 38px; }
}
</style>

    </div>

    <!-- RECENT LABELS SIDEBAR -->
    <div class="panel sidebar-panel" style="background: var(--bg-page); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 20px;">

        <!-- LIVE PREVIEW SECTION -->
        <div class="live-preview-container">
            <h3 style="margin-bottom: 12px; font-size: 0.9rem; color: var(--text-secondary); display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--accent-color); color: #fff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">LIVE</span>
                Label Preview (2x1)
            </h3>

            <div class="label-mockup" id="liveLabelPreview">
                <div class="label-mockup-header">
                    <div id="prevBrandModel" class="prev-main">BRAND MODEL</div>
                    <div id="prevSeriesSpecs" class="prev-sub">SERIES SPECS</div>
                </div>
                <div class="label-mockup-grid">
                    <div class="prev-item">CPU: <span id="prevCpu">i5-8th</span></div>
                    <div class="prev-item">RAM: <span id="prevRam">16GB</span></div>
                    <div class="prev-item">SSD: <span id="prevStorage">512GB</span></div>
                    <div class="prev-item">BATT: <span id="prevBattery">YES</span></div>
                </div>
                <div class="label-mockup-footer">
                    <div id="prevSN">S/N: 000000</div>
                    <div id="prevCond">UNTESTED</div>
                </div>
            </div>
            <p style="font-size: 0.65rem; color: var(--text-secondary); margin-top: 8px; font-style: italic; text-align: left;">
                Visual approximation of 2" x 1" thermal output.
            </p>
        </div>

        <div class="form-divider" style="margin: 0;"></div>

        <h3 style="margin-bottom: 0px; font-size: 1rem; color: var(--text-secondary); display: flex; justify-content: space-between; align-items: center;">
            Recently Added
            <span style="font-size: 0.7rem; font-weight: normal; background: var(--border-color); padding: 2px 6px; border-radius: 4px;">Clone Tools</span>
        </h3>
        <?php if (empty($recentLabels)): ?>
            <p style="font-size: 0.85rem; color: var(--text-secondary); font-style: italic;">No recent work found.</p>
        <?php else: ?>
            <div class="recent-scroll-container">
                <?php foreach ($recentLabels as $rl): ?>
                    <div class="recent-card clone-trigger"
                         data-brand="<?= htmlspecialchars($rl['brand'] ?? '') ?>"
                         data-model="<?= htmlspecialchars($rl['model'] ?? '') ?>"
                         data-series="<?= htmlspecialchars($rl['series'] ?? '') ?>"
                         data-cpu-gen="<?= htmlspecialchars($rl['cpu_gen'] ?? '') ?>"
                         data-cpu-specs="<?= htmlspecialchars($rl['cpu_specs'] ?? '') ?>"
                         data-cpu-cores="<?= htmlspecialchars($rl['cpu_cores'] ?? '') ?>"
                         data-cpu-speed="<?= htmlspecialchars($rl['cpu_speed'] ?? '') ?>"
                         data-ram="<?= htmlspecialchars($rl['ram'] ?? '') ?>"
                         data-storage="<?= htmlspecialchars($rl['storage'] ?? '') ?>"
                         title="Click to clone these specs">
                        <div style="font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars(($rl['brand'] ?? '') . ' ' . ($rl['model'] ?? '')) ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars(($rl['cpu_gen'] && $rl['cpu_gen'] !== '0') ? $rl['cpu_gen'] : 'Unknown Gen') ?> |
                            <?= htmlspecialchars(($rl['ram'] && $rl['ram'] !== '0') ? (stripos($rl['ram'], 'GB') !== false ? $rl['ram'] : $rl['ram'] . 'GB') : 'No RAM') ?>
                        </div>
                        <div style="margin-top: 8px; display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 8px;">
                            <span style="font-size: 0.7rem; color: var(--accent-color); font-weight: bold;">#<?= str_pad($rl['id'], 5, '0', STR_PAD_LEFT) ?></span>
                            <span style="font-size:0.75rem; color:var(--text-main); font-weight: 800; background: rgba(0,0,0,0.05); padding: 2px 8px; border-radius: 20px;">📋 CLONE</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
.mobile-sticky-bar {
    display: none;
    position: sticky;
    bottom: -1px; /* Stays at bottom of scroll */
    left: 0;
    right: 0;
    padding: 15px 20px;
    background: var(--bg-panel);
    border-top: 1px solid var(--border-color);
    box-shadow: 0 -10px 20px rgba(0,0,0,0.05);
    z-index: 500;
}

.btn-large {
    height: 56px;
    font-size: 1.1rem;
    font-weight: 800;
}

.recent-scroll-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.recent-card {
    padding: 12px;
    background: var(--bg-panel);
    border-radius: 10px;
    font-size: 0.85rem;
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.2s ease;
}

.recent-card:active {
    background: #f8fafc;
    transform: scale(0.97);
}

@media (max-width: 1100px) {
    .mobile-header-panel {
        text-align: center;
    }
    .mobile-header-panel h1 { font-size: 1.5rem; margin-bottom: 5px; }

    .sidebar-panel {
        order: -1; /* Recent labels at top on mobile for quick access */
        margin-bottom: 10px;
    }

    .recent-scroll-container {
        flex-direction: row;
        overflow-x: auto;
        padding-bottom: 5px;
        -webkit-overflow-scrolling: touch;
    }

    .recent-card {
        min-width: 180px;
        flex-shrink: 0;
    }

    .mobile-sticky-bar {
        display: block;
        padding-bottom: calc(15px + env(safe-area-inset-bottom));
    }

    .desktop-only {
        display: none !important;
    }

    .form-panel {
        margin-bottom: 100px; /* Space for sticky bar */
    }
}
</style>

<!-- Add the dynamic JS controls -->
<script src="assets/js/forms.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-new-label').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
