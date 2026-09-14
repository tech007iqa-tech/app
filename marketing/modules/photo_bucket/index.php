<?php
/**
 * Photo Bucket Module - Marketing Hub
 * Handles management of hardware and marketing images.
 */

$marketingDb = get_marketing_db();
$warehouseDb = get_warehouse_db();
$labelsDb = get_labels_db();

require_once __DIR__ . '/../../includes/photo_processor.php';
$processor = new PhotoProcessor($marketingDb);

require_once __DIR__ . '/functions.php';

// Handle any POST actions (guarded with CSRF)
handle_photo_bucket_actions($marketingDb, $processor, $labelsDb, $warehouseDb);

// Fetch Data safely
$photos = [];
try {
    $photos = $marketingDb->query("SELECT * FROM photos ORDER BY created_at DESC")->fetchAll();
} catch (Throwable $e) {
    error_log("Failed to fetch photos: " . $e->getMessage());
}

$warehouseCount = 0;
$marketingCount = 0;
foreach ($photos as $p) {
    if (($p['source'] ?? '') === 'warehouse') {
        $warehouseCount++;
    } else {
        $marketingCount++;
    }
}

$models = get_photo_bucket_models($marketingDb, $labelsDb, $warehouseDb);
?>

<!-- Module Specific Styles -->
<link rel="stylesheet" href="assets/css/modules/photo_bucket.css?v=<?= filemtime(__DIR__ . '/../../assets/css/modules/photo_bucket.css'); ?>">

<header class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="display: flex; align-items: center; gap: 10px; margin: 0 0 0.25rem 0;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, rgba(0, 114, 104, 0.15), rgba(2, 132, 199, 0.15)); color: var(--accent-primary); border: 1px solid rgba(0, 114, 104, 0.2);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </span>
                Photo Bucket
            </h1>
            <p>Manage your marketing assets and hardware photography.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <form action="?page=photo_bucket" method="POST" style="margin: 0;">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="sync_warehouse_photos" value="1">
                <button type="submit" class="btn-action" style="background: #0284c7; color: #ffffff; min-width: 175px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;" title="Scan and sync photography from warehouse inventory locations">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    Sync Warehouse
                </button>
            </form>
            <?php if (extension_loaded('gd')): ?>
                <form action="?page=photo_bucket" method="POST" style="margin: 0;">
                    <?= UI::csrf_field() ?>
                    <input type="hidden" name="regenerate_thumbnails" value="1">
                    <button type="submit" class="btn-action" style="background: var(--accent-secondary); color: var(--text-main); min-width: 160px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                        Regenerate
                    </button>
                </form>
            <?php endif; ?>
            <button onclick="document.getElementById('upload-modal').style.display='flex'" class="btn-action" style="min-width: 160px; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                Upload New Photo
            </button>
        </div>
    </div>
</header>

<?php if (!extension_loaded('gd')): ?>
    <div class="alert alert-danger" style="background: #fff1f2; color: #9f1239; border-color: #fda4af;">
        <strong>⚠️ Performance Warning:</strong> The PHP 'GD' library is not enabled on your server. High-resolution photos will be used directly, which may slow down the gallery. Enable 'gd' in your php.ini for automatic thumbnail optimization.
    </div>
    <?php
    $marketingDb->exec("UPDATE photos SET status = 'Ready' WHERE status = 'Processing'");
    ?>
<?php endif; ?>

<!-- Source Filter Bar -->
<div class="photo-filter-bar" style="display: flex; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap;">
    <div class="filter-pills" id="photo-source-pills" style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="filter-pill active" onclick="filterPhotoSource('all', this)" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            All Photos (<?= count($photos); ?>)
        </button>
        <button type="button" class="filter-pill" onclick="filterPhotoSource('warehouse', this)" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            Warehouse Synced (<?= $warehouseCount; ?>)
        </button>
        <button type="button" class="filter-pill" onclick="filterPhotoSource('upload', this)" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            Uploaded Originals (<?= $marketingCount; ?>)
        </button>
    </div>
    <div style="font-size: 0.85rem; color: var(--text-dim);">
        Showing <span id="photo-visible-count" style="font-weight: 700; color: var(--accent-primary);"><?= count($photos); ?></span> of <?= count($photos); ?> assets
    </div>
</div>

<div class="photo-grid">
    <?php if (empty($photos)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 4rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📸</div>
            <h2 style="margin-bottom: 0.5rem;">No Photos Found</h2>
            <p style="color: var(--text-dim);">Start by uploading some hardware photos for your marketing campaigns or click <strong>🔄 Sync Warehouse</strong>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($photos as $photo): ?>
            <div class="photo-card-compact" data-source="<?= h($photo['source'] ?? 'upload'); ?>">
                <div class="photo-thumb-container">
                    <?php
                    $displayImg = (!empty($photo['thumbnail_path']) && file_exists(__DIR__ . '/../../' . $photo['thumbnail_path']))
                                  ? $photo['thumbnail_path']
                                  : $photo['file_path'];
                    $fullViewPath = (!empty($photo['optimized_path']) && file_exists(__DIR__ . '/../../' . $photo['optimized_path']))
                                    ? $photo['optimized_path']
                                    : $photo['file_path'];
                    ?>
                    <img src="<?= h($displayImg); ?>" alt="<?= h($photo['original_name'] ?? 'Hardware Photo'); ?>"
                         style="<?= ($photo['status'] === 'Processing') ? 'filter: blur(8px);' : ''; ?>">

                    <?php if ($photo['status'] === 'Processing'): ?>
                        <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.6); color: var(--accent-primary); z-index: 6;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </div>
                    <?php endif; ?>

                    <div class="photo-actions-overlay">
                        <!-- Top-Right: Download Action -->
                        <a href="<?= h($photo['file_path']); ?>" download class="action-icon-small download" title="Download Raw Image">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                        </a>

                        <!-- Center: Tiny Image of Itself Preview -->
                        <a href="<?= h($fullViewPath); ?>" target="_blank" class="action-mini-preview" title="View Full High-Res Photo">
                            <img src="<?= h($displayImg); ?>" alt="Preview" class="mini-preview-thumb">
                            <span class="mini-preview-badge">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h6v6"></path>
                                    <path d="M9 21H3v-6"></path>
                                    <path d="M21 3l-7 7"></path>
                                    <path d="M3 21l7-7"></path>
                                </svg>
                            </span>
                        </a>

                        <!-- Bottom-Left: Delete Action -->
                        <form action="?page=photo_bucket" method="POST" class="delete-photo-form" onsubmit="return confirmAction('Delete this photo permanently from the asset bucket?', this);">
                            <?= UI::csrf_field() ?>
                            <input type="hidden" name="delete_photo" value="<?= (int)$photo['id']; ?>">
                            <button type="submit" class="action-icon-small delete" title="Delete Photo">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="photo-meta-compact">
                    <h3 title="<?= h($photo['model_name'] ?: 'General'); ?>">
                        <?= h($photo['model_name'] ?: 'General'); ?>
                    </h3>
                    <div class="category-row">
                        <span><?= h($photo['category'] ?? 'General'); ?></span>
                        <?php if (!empty($photo['location_code'])): ?>
                            <span class="badge" style="background: rgba(2, 132, 199, 0.12); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3); font-size: 0.6rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;" title="Warehouse Location: <?= h($photo['location_code']); ?>">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                <?= h($photo['location_code']); ?>
                            </span>
                        <?php endif; ?>
                        <button type="button" class="btn-copy-path" onclick="copyToClipboard('<?= h($fullViewPath); ?>', 'Asset Path')" title="Copy Asset Path">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- UPLOAD MODAL -->
<div id="upload-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 500px; animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="margin: 0;">Upload Marketing Photo</h2>
            <button type="button" onclick="document.getElementById('upload-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dim);">×</button>
        </div>

        <form action="?page=photo_bucket" method="POST" enctype="multipart/form-data" class="standard-form">
            <?= UI::csrf_field() ?>
            <div class="form-group">
                <label for="photo">Select Photo (JPG, PNG, WEBP, GIF - max 15MB)</label>
                <input type="file" name="photo" id="photo" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" required>
            </div>

            <div class="form-group">
                <label for="model_name">Hardware Model (Optional)</label>
                <input type="text" name="model_name" id="model_name" list="model_list" placeholder="Start typing or enter custom model...">
                <datalist id="model_list">
                    <?php foreach ($models as $model): ?>
                        <option value="<?= h($model); ?>">
                    <?php endforeach; ?>
                </datalist>
                <small style="font-size: 0.7rem; color: var(--text-dim); display: block; margin-top: 4px;">Suggestions include Active Templates and Warehouse Inventory.</small>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select name="category" id="category">
                    <option value="Laptop">Laptop</option>
                    <option value="Workstation">Workstation</option>
                    <option value="Monitor">Monitor</option>
                    <option value="Parts">Parts</option>
                    <option value="Bulk Stock">Bulk Stock</option>
                    <option value="Marketing Banner">Marketing Banner</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="submit" name="upload_photo" class="btn-action" style="flex: 2;">Upload to Bucket</button>
                <button type="button" onclick="document.getElementById('upload-modal').style.display='none'" class="btn-action" style="flex: 1; background: var(--text-dim);">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.9) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
</style>

<script>
function filterPhotoSource(source, btn) {
    document.querySelectorAll('#photo-source-pills .filter-pill').forEach(function(el) {
        el.classList.remove('active');
    });
    if (btn) btn.classList.add('active');

    var cards = document.querySelectorAll('.photo-card-compact');
    var visible = 0;
    cards.forEach(function(card) {
        var cardSource = card.getAttribute('data-source') || 'upload';
        if (source === 'all' || cardSource === source) {
            card.style.display = '';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });
    var countSpan = document.getElementById('photo-visible-count');
    if (countSpan) countSpan.textContent = visible;
}
</script>
