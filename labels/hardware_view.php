<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;

if ($id > 0) {
    try {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
    } catch (PDOException $e) {
        // Error handling
    }
}

// Redirect if not found
if (!$item) {
    echo "<div class='panel'><h1>404 Not Found</h1><p>This item does not exist.</p><a href='labels.php' class='btn btn-primary'>Back to Inventory</a></div>";
    require_once 'includes/footer.php';
    exit;
}

$desc  = $item['description'] ?? 'Untested';
$color = $desc === 'For Parts' ? 'var(--btn-danger-bg)'
       : ($desc === 'Refurbished' ? 'var(--btn-success-bg)' : '#f39c12');
?>

<div class="panel flex-between" style="margin-bottom: var(--spacing);">
    <div>
        <h1 style="color: <?= $color ?>;">🛠️ <?= htmlspecialchars($desc) ?> Technical Sheet</h1>
        <p>Detailed specifications for <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['model'] . ' ' . ($item['series'] ?? '')) ?></strong></p>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button id="btnLaunchODT" class="btn"
                data-id="<?= (int)$item['id'] ?>"
                data-brand="<?= htmlspecialchars($item['brand']) ?>"
                data-model="<?= htmlspecialchars($item['model']) ?>"
                style="padding:8px 18px; background: var(--text-main); color: white;">
            🏷️ Launch Label
        </button>
        <button id="btnDelete" class="btn btn-danger"
                data-id="<?= (int)$item['id'] ?>"
                data-label="<?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>"
                style="padding:8px 18px;">
            🗑️ Delete
        </button>
        <a href="labels.php" class="btn" style="background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-secondary); padding:8px 18px;">← Back</a>
    </div>
</div>

<!-- Responsive Layout Styling -->
<style>
    .technical-layout {
        display: grid;
        grid-template-columns: 1fr 350px;
        grid-template-areas: "main sidebar";
        gap: var(--spacing);
        align-items: start;
    }
    .main-technical-panel { grid-area: main; }
    .summary-sidebar { grid-area: sidebar; }

    @media (max-width: 1024px) {
        .technical-layout {
            grid-template-columns: 1fr;
            grid-template-areas: "sidebar" "main";
        }
        .summary-sidebar { margin-bottom: 0; }
    }
</style>

<!-- Main Content Grid -->
<form id="refurbForm">
    <div class="technical-layout">

        <!-- LEFT: Technical Specs & Baseline Form -->
        <div class="panel main-technical-panel">
            <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                Detailed Hardware Profile
            </h3>

            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<?php
    $formType = 'edit';
    include 'includes/hardware_form.php';
?>

            <hr style="border:0; border-top:1px solid var(--border-color); margin: 25px 0;">

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <div id="saveStatus" style="margin-right: auto; line-height: 40px; font-size: 0.9rem;"></div>
                <button type="button" id="saveRefurbBtn" class="btn btn-success" style="padding: 10px 30px; font-weight: bold;">
                    💾 Update Hardware Profile
                </button>
            </div>
        </div>

        <!-- RIGHT: Summary Sidebar (Context) -->
        <div class="panel summary-sidebar" style="background: var(--bg-page); border: 2px dashed var(--border-color);">
            <h3 style="margin-bottom: 15px; font-size: 1rem; color: var(--text-secondary);">Inventory Info</h3>
            <div style="font-size: 0.9rem; line-height: 1.8;">
                <div class="flex-between"><span>Added Date:</span> <strong><?= format_date($item['created_at']) ?></strong></div>
                <div class="flex-between"><span>Current Status:</span> <strong style="color: var(--btn-success-bg);"><?= htmlspecialchars($item['status'] ?? '—') ?></strong></div>
                <div class="flex-between"><span>Warehouse Loc:</span> <strong><?= htmlspecialchars($item['warehouse_location'] ?? '—') ?></strong></div>

                <!-- Quick Specs Summary (New Section) -->
                <div style="margin: 15px 0; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 8px;">
                     <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px; letter-spacing: 0.5px;">Quick Specs</div>
                     <div class="flex-between"><span>CPU Gen:</span> <strong><?= htmlspecialchars($item['cpu_gen'] ?: '—') ?></strong></div>
                     <div class="flex-between"><span>Series:</span> <strong><?= htmlspecialchars($item['series'] ?: '—') ?></strong></div>
                     <div class="flex-between"><span>RAM:</span> <strong><?= htmlspecialchars($item['ram'] ?: 'None') ?></strong></div>
                     <div class="flex-between"><span>Storage:</span> <strong><?= htmlspecialchars($item['storage'] ?: 'None') ?></strong></div>

                     <details style="margin-top: 10px; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 10px;">
                        <summary style="font-size: 0.75rem; font-weight: 700; color: var(--accent-color); cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            🔍 Full Technical Snapshot
                        </summary>
                        <div style="margin-top: 8px; font-size: 0.8rem; color: var(--text-secondary); display: grid; gap: 4px;">
                            <div class="flex-between"><span>Full Processor:</span> <span style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($item['cpu_specs'] ?: '—') ?></span></div>
                            <div class="flex-between"><span>GPU (Video):</span> <span style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($item['gpu'] ?: 'Integrated') ?></span></div>
                            <div class="flex-between"><span>Battery:</span> <span style="color: var(--text-main); font-weight: 600;"><?= (int)($item['battery'] ?? 0) === 1 ? 'Included' : 'N/A' ?></span></div>
                            <div class="flex-between"><span>BIOS State:</span> <span style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($item['bios_state'] ?: 'Unknown') ?></span></div>
                            <div class="flex-between"><span>OS Version:</span> <span style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($item['os_version'] ?: 'None') ?></span></div>
                        </div>
                     </details>
                </div>
            </div>

            <div style="margin-top: 10px; padding: 15px; background: rgba(140, 198, 63, 0.1); border-radius: 8px; color: var(--accent-hover); font-size: 0.85rem;">
                💡 <strong>Sales Note:</strong> The technical sheet is the primary source for buyers. Keeping CPU, RAM and and health updated here will push accurate data to the Purchase Order.
            </div>
        </div>

    </div>
</form>

<!-- Add the dynamic JS controls -->
<script src="assets/js/forms.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Set active link in sidebar
    const navLink = document.getElementById('nav-labels');
    if (navLink) navLink.classList.add('active');

    const refurbForm = document.getElementById('refurbForm');
    const saveBtn    = document.getElementById('saveRefurbBtn');
    const statusMsg  = document.getElementById('saveStatus');

    // ── SAVE ────────────────────────────────────────────────────────────────
    saveBtn.addEventListener('click', () => {
        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ Saving...';
        statusMsg.textContent = '';
        statusMsg.style.color = 'var(--text-secondary)';

        const formData = new FormData(refurbForm);

        fetch('api/edit_label.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                statusMsg.textContent = '✅ Technical sheet updated successfully!';
                statusMsg.style.color = 'var(--btn-success-bg)';
                setTimeout(() => { statusMsg.textContent = ''; }, 3000);
            } else {
                statusMsg.textContent = '❌ Error: ' + (json.error || 'Unknown error');
                statusMsg.style.color = 'var(--btn-danger-bg)';
            }
        })
        .catch(() => {
            statusMsg.textContent = '❌ Network error.';
            statusMsg.style.color = 'var(--btn-danger-bg)';
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.textContent = '💾 Update Technical Sheet';
        });
    });

    // ── LAUNCH ODT (Always Regenerate & Open) ───────────────────────────────
    document.getElementById('btnLaunchODT').addEventListener('click', async function() {
        const btn   = this;
        const id    = btn.dataset.id;
        const brand = btn.dataset.brand;
        const model = btn.dataset.model;
        await flashOpenLabel(id, brand, model, btn);
    });

    // ── DELETE ───────────────────────────────────────────────────────────────
    document.getElementById('btnDelete').addEventListener('click', function() {
        const id    = this.dataset.id;
        const label = this.dataset.label;

        if (!confirm(`Delete "${label}" from the warehouse?\n\nThis cannot be undone.`)) return;

        const formData = new FormData();
        formData.append('id', id);

        fetch('api/delete_label.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                window.location.href = 'labels.php';
            } else {
                alert('Delete failed: ' + (json.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error — item was not deleted.'));
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
