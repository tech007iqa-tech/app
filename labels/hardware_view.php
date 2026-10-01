<?php
// labels/hardware_view.php
// Detailed Hardware Technical Sheet & Profile Editor
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hardware_mapping.php';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;

if ($id > 0) {
    try {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

if (!$item) {
    echo "<div class='panel' style='text-align:center; padding:60px 20px;'>
            <h1 style='font-size:2rem; margin-bottom:12px;'>⚠️ Hardware Record Not Found</h1>
            <p style='color:var(--text-secondary); margin-bottom:24px;'>The item with ID #$id does not exist or was removed.</p>
            <a href='labels.php' class='btn btn-primary'>← Return to Inventory</a>
          </div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$brandModel = htmlspecialchars(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '') . ' ' . ($item['series'] ?? ''));
$cond = $item['description'] ?? 'Untested';
$badgeClass = $cond === 'Refurbished' ? 'badge-success' : ($cond === 'For Parts' ? 'badge-danger' : 'badge-warning');
?>

<!-- BREADCRUMB & HEADER -->
<div class="panel flex-between" style="margin-bottom: 24px; padding: 20px 28px;">
    <div>
        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px;">
            <a href="labels.php" style="color: var(--accent-color); font-weight: bold;">📦 Inventory</a>
            <span> / </span>
            <span>Profile #<?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></span>
        </div>
        <h1 style="font-size: 1.6rem; font-weight: 900; margin-bottom: 4px;">
            🛠️ <?= $brandModel ?>
        </h1>
        <div style="display: flex; gap: 8px; align-items: center; margin-top: 6px;">
            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($cond) ?></span>
            <span class="badge badge-neutral">📍 LOC: <?= htmlspecialchars($item['warehouse_location'] ?: 'Unassigned') ?></span>
            <span style="font-size: 0.75rem; font-family: var(--font-mono); color: var(--text-secondary);">S/N: <?= htmlspecialchars($item['serial_number'] ?: '—') ?></span>
        </div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" class="btn btn-success" onclick="printThermalLabel(<?= (int)$item['id'] ?>)">
            <span>🖨️ Thermal Print</span>
        </button>
        <button type="button" class="btn btn-dark" onclick="flashOpenLabel(<?= (int)$item['id'] ?>, '<?= addslashes($item['brand']) ?>', '<?= addslashes($item['model']) ?>', this)">
            <span>📄 Windows ODT</span>
        </button>
        <button type="button" class="btn btn-danger" id="btnDeleteRecord">
            <span>🗑️ Delete</span>
        </button>
        <a href="labels.php" class="btn btn-secondary">← Back</a>
    </div>
</div>

<div class="hardware-view-grid">

    <!-- LEFT COLUMN: TECHNICAL SHEET EDITOR -->
    <div class="hardware-editor-column">
        <form id="refurbForm">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

            <?php
                $formType = 'edit';
                include __DIR__ . '/includes/hardware_form.php';
            ?>

            <div class="panel" style="display: flex; justify-content: space-between; align-items: center; padding: 18px 24px;">
                <span id="saveStatus" style="font-size: 0.9rem; font-weight: 700;"></span>
                <button type="button" id="saveRefurbBtn" class="btn btn-success btn-large">
                    <span>💾 Save &amp; Update Technical Profile</span>
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT COLUMN: STICKER PREVIEW & CONTEXT -->
    <aside class="hardware-sidebar-column">

        <!-- LIVE STICKER PREVIEW -->
        <div class="live-sticker-preview-box">
            <div class="preview-box-header">
                <div class="preview-box-title">
                    <span style="color:var(--accent-color);">●</span> 2" × 1" Label Preview
                </div>
            </div>

            <!-- MOCKUP DISPLAY -->
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div class="thermal-sticker-mockup" id="mockupLabelA">
                    <div class="mockup-branding">
                        <div class="mockup-brand-model" id="prevBrandModel"><?= htmlspecialchars(($item['brand'] ?? '') . ' ' . ($item['model'] ?? '')) ?></div>
                        <div class="mockup-series" id="prevSeries"><?= htmlspecialchars($item['series'] ?? '') ?></div>
                        <div class="mockup-cpu" id="prevCpu"><?= htmlspecialchars($item['cpu_specs'] ?: ($item['cpu_gen'] ?: 'Processor N/A')) ?></div>
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
                        <div class="mockup-sn" id="prevSN">S/N: <?= htmlspecialchars($item['serial_number'] ?: 'XXXXXX') ?></div>
                    </div>

                    <div class="mockup-footer">
                        <span>#<?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        <span id="prevLoc">LOC: <?= htmlspecialchars($item['warehouse_location'] ?: '—') ?></span>
                        <span id="prevCond"><?= strtoupper($item['description'] ?: 'UNTESTED') ?></span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <button type="button" class="btn btn-sm btn-success" onclick="printThermalLabel(<?= (int)$item['id'] ?>)">🖨️ Thermal</button>
                <button type="button" class="btn btn-sm btn-dark" onclick="flashOpenLabel(<?= (int)$item['id'] ?>, '<?= addslashes($item['brand']) ?>', '<?= addslashes($item['model']) ?>', this)">📄 ODT</button>
            </div>
        </div>

        <!-- AUDIT & HARDWARE METADATA -->
        <div class="panel" style="margin-top: 20px; padding: 20px;">
            <h4 style="font-size: 0.9rem; font-weight: 800; color: var(--text-secondary); margin-bottom: 14px;">
                📋 Hardware Metadata
            </h4>
            <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 8px;">
                <div class="flex-between">
                    <span style="color: var(--text-muted);">Database ID:</span>
                    <strong>#<?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                </div>
                <div class="flex-between">
                    <span style="color: var(--text-muted);">Intake Date:</span>
                    <strong><?= format_date($item['created_at']) ?></strong>
                </div>
                <div class="flex-between">
                    <span style="color: var(--text-muted);">Last Updated:</span>
                    <strong><?= format_date($item['updated_at'] ?? $item['created_at']) ?></strong>
                </div>
                <div class="flex-between">
                    <span style="color: var(--text-muted);">Warehouse Loc:</span>
                    <strong style="color: var(--accent-color);">📍 <?= htmlspecialchars($item['warehouse_location'] ?: 'Unassigned') ?></strong>
                </div>
            </div>
        </div>

    </aside>

</div>

<style>
.hardware-view-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1024px) {
    .hardware-view-grid {
        grid-template-columns: 1fr;
    }
    .hardware-sidebar-column {
        order: -1;
    }
}
</style>

<!-- Modular Form JS for Sync & Preview -->
<script src="assets/js/forms.js?v=<?= filemtime(__DIR__ . '/assets/js/forms.js') ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Force Advanced diagnostic section to be visible in hardware_view
    switchFormMode('advanced');

    const saveBtn = document.getElementById('saveRefurbBtn');
    const form = document.getElementById('refurbForm');
    const statusMsg = document.getElementById('saveStatus');

    if (saveBtn && form) {
        saveBtn.addEventListener('click', async () => {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span>⏳ Saving Changes...</span>';

            const formData = new FormData(form);

            try {
                const res = await fetch('api/edit_label.php', { method: 'POST', body: formData });
                const json = await res.json();

                if (json.success) {
                    Toast.success("Hardware specifications updated successfully!");
                    statusMsg.innerHTML = '<span style="color:var(--accent-color);">✅ Updated!</span>';
                    setTimeout(() => { statusMsg.innerHTML = ''; }, 3000);
                } else {
                    Toast.error("Update failed: " + (json.error || ''));
                    statusMsg.innerHTML = '<span style="color:var(--color-danger);">❌ Error</span>';
                }
            } catch (err) {
                Toast.error("Network error saving changes.");
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<span>💾 Save &amp; Update Technical Profile</span>';
            }
        });
    }

    // Delete Button
    const delBtn = document.getElementById('btnDeleteRecord');
    if (delBtn) {
        delBtn.addEventListener('click', () => {
            if (!confirm("Permanently delete this hardware profile from warehouse inventory?\n\nThis cannot be undone.")) return;

            const fd = new FormData();
            fd.append('id', <?= (int)$item['id'] ?>);

            fetch('api/delete_label.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    window.location.href = 'labels.php';
                } else {
                    alert("Delete failed: " + (json.error || ''));
                }
            })
            .catch(() => alert("Network error deleting record."));
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
