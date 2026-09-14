<?php
/**
 * Warehouse Dialog Modals Partial
 * Encapsulates modal windows for managing zone names, statuses, and location photographs.
 */
?>
<!-- Rename Zone Modal -->
<div id="rename-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;"
    onclick="if(event.target===this) closeRenameModal()">
    <div
        style="background:white; border-radius:24px; width:95%; max-width:450px; padding:35px; box-shadow:var(--shadow-lg); position:relative;">
        <form method="POST" id="delete-zone-form"
            onsubmit="return confirm('CRITICAL ACTION: This will PERMANENTLY DELETE THIS LOCATION and ALL ITEMS in it. This cannot be undone. Proceed?');">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="delete_zone">
            <input type="hidden" name="old_loc" id="delete-zone-loc">
            <input type="hidden" name="active_zone" value="<?= htmlspecialchars($active_zone_name ?? '') ?>">
            <button type="submit" class="btn-danger-icon" title="Delete Location"
                style="position:absolute; top:20px; right:20px; background:#fee2e2; color:#ef4444; border:1px solid #fecaca; border-radius:10px; cursor:pointer; font-size:0.85rem; padding:6px 12px; font-weight:700; display:flex; align-items:center; gap:5px;">
                🗑️ <span>Delete</span>
            </button>
        </form>

        <h2 style="font-weight:900; margin-bottom:10px; font-size:1.25rem;">📦 Manage Working Zone</h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom:25px;">Update the name or operational status of this location.</p>

        <form method="POST" onsubmit="submitRenameZoneAjax(event)">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="rename_zone">
            <input type="hidden" name="old_loc" id="rename-old-loc">

            <div class="form-group" style="margin-bottom:20px;">
                <label for="rename-new-loc"
                    style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Zone Name</label>
                <input type="text" name="new_loc" id="rename-new-loc" required
                    style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:800; font-size:1rem;">
            </div>

            <div class="form-group" style="margin-bottom:30px;">
                <label for="rename-status"
                    style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Location Status</label>
                <select name="location_status" id="rename-status"
                    style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:700; cursor:pointer; background:#f8fafc;">
                    <?php foreach ($all_statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status['name']) ?>">
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top:10px; text-align:right;">
                    <a href="javascript:void(0)" onclick="toggleManageStatuses()"
                        style="font-size:0.7rem; color:var(--accent-color); font-weight:800; text-decoration:none;">+ Add New Status Type</a>
                </div>
            </div>

            <div id="manage-statuses-block"
                style="display:none; background:#f1f5f9; padding:20px; border-radius:16px; margin-bottom:25px; border:1px dashed #cbd5e1;">
                <div
                    style="font-size:0.7rem; font-weight:900; text-transform:uppercase; color:#64748b; margin-bottom:10px;">
                    Create New Status</div>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="new-status-name" placeholder="Status Name"
                        style="flex:2; height:38px; border-radius:8px; border:1px solid #cbd5e1; padding:0 10px; font-size:0.85rem;">
                    <input type="color" id="new-status-color" value="#64748b"
                        style="flex:0.5; height:38px; border:none; padding:0; background:none; cursor:pointer;">
                    <button type="button" onclick="addNewStatusType()"
                        style="flex:1; background:var(--accent-color); color:white; border:none; border-radius:8px; font-weight:800; font-size:0.75rem; cursor:pointer;">Apply</button>
                </div>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="button" onclick="closeRenameModal()"
                    style="flex:1; height:48px; border-radius:14px; border:1px solid #ddd; background:none; font-weight:800; cursor:pointer; color:#64748b;">Cancel</button>
                <button type="submit"
                    style="flex:1; height:48px; border-radius:14px; border:none; background:var(--text-main); color:white; font-weight:800; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">Update Zone</button>
            </div>
        </form>
    </div>
</div>

<?php if ($is_spreadsheet && $selected_loc): ?>
<!-- Upload Location Photo Modal -->
<div id="upload-photo-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2200; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 450px; padding: 1.5rem; animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: #ffffff; color: #1e293b; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.25rem;">📸 Upload Photo for <?= htmlspecialchars($selected_loc) ?></h3>
            <button type="button" onclick="document.getElementById('upload-photo-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dim);">×</button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="standard-form">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="upload_location_photo">
            <input type="hidden" name="location_code" value="<?= htmlspecialchars($selected_loc) ?>">
            <input type="hidden" name="sector" value="<?= htmlspecialchars($selected_sector) ?>">
            <input type="hidden" name="redirect_to" value="location">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Select Photo</label>
                <input type="file" name="photo" accept="image/*" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-body);">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Layer / Category</label>
                <select name="category" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-body); color: var(--text-main);">
                    <option value="Layer 1 (Bottom)">Layer 1 (Bottom)</option>
                    <option value="Layer 2">Layer 2</option>
                    <option value="Layer 3">Layer 3</option>
                    <option value="Layer 4">Layer 4</option>
                    <option value="Layer 5 (Top)">Layer 5 (Top)</option>
                    <option value="Row View">Row / Overall View</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn-action" style="flex: 2; padding: 10px; background: var(--accent-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Upload Photo</button>
                <button type="button" onclick="document.getElementById('upload-photo-modal').style.display='none'" class="btn-action" style="flex: 1; padding: 10px; background: var(--text-dim); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($active_zone_name)): ?>
<!-- Zone Photos Modal -->
<div id="zone-photos-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 800px; max-height: 85vh; padding: 1.5rem; animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: #ffffff; color: #1e293b; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); display: flex; flex-direction: column; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-shrink: 0;">
            <h3 style="margin: 0; font-size: 1.25rem;">📸 Photos for Zone: <?= htmlspecialchars($active_zone_name) ?></h3>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="document.getElementById('zone-upload-photo-modal').style.display='flex'" class="btn-action" style="background: var(--accent-color); color: white; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">Upload New Photo</button>
                <button type="button" onclick="document.getElementById('zone-photos-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dim);">×</button>
            </div>
        </div>

        <div style="overflow-y: auto; flex-grow: 1; padding-right: 5px;">
            <?php if (empty($zone_photos)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-dim);">
                    <div style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;">📸</div>
                    <p>No photos uploaded for any location inside Zone: <?= htmlspecialchars($active_zone_name) ?> yet.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem;">
                    <?php foreach ($zone_photos as $photo): ?>
                        <div class="photo-card-mini-zone" style="border: 1px solid var(--border-color); border-radius: 8px; padding: 6px; background: var(--bg-body); text-align: center;">
                            <div class="img-preview-container-zone" style="position: relative; width: 100%; height: 130px; overflow: hidden; border-radius: 6px; cursor: pointer;">
                                <img src="<?= htmlspecialchars($photo['thumbnail_path']) ?>" alt="<?= htmlspecialchars($photo['original_filename']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <div class="hover-preview-zone" style="display: none; position: fixed; z-index: 2100; width: 450px; height: 350px; background: rgba(0,0,0,0.95); border: 2px solid var(--accent-primary); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden; pointer-events: none;">
                                    <img src="<?= htmlspecialchars($photo['optimized_path']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                                </div>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 700; margin-top: 6px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                Location: <?= htmlspecialchars($photo['location_code']) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 2px;">
                                Sector: <?= htmlspecialchars($photo['sector']) ?>
                            </div>
                            <div style="font-size: 0.75rem; font-weight: 600; color: var(--accent-color); margin-top: 2px;">
                                <?= htmlspecialchars($photo['category']) ?>
                            </div>
                            <div style="display: flex; justify-content: center; gap: 10px; margin-top: 6px;">
                                <a href="download_archive.php?id=<?= $photo['id'] ?>" class="btn-icon-tiny" title="Download Raw Original" style="font-size: 0.85rem; text-decoration: none;">📥</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this photo?');">
                                    <?= UI::csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_location_photo">
                                    <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                    <input type="hidden" name="location_code" value="<?= htmlspecialchars($photo['location_code']) ?>">
                                    <input type="hidden" name="sector" value="<?= htmlspecialchars($photo['sector']) ?>">
                                    <input type="hidden" name="redirect_to" value="zone">
                                    <input type="hidden" name="active_zone" value="<?= htmlspecialchars($active_zone_name) ?>">
                                    <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer; font-size: 0.85rem;" title="Delete Photo">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Zone Upload Photo Modal -->
<div id="zone-upload-photo-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2200; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 450px; padding: 1.5rem; animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: #ffffff; color: #1e293b; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.25rem;">Upload Photo for Zone: <?= htmlspecialchars($active_zone_name) ?></h3>
            <button type="button" onclick="document.getElementById('zone-upload-photo-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dim);">×</button>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="standard-form">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="upload_location_photo">
            <input type="hidden" name="redirect_to" value="zone">
            <input type="hidden" name="active_zone" value="<?= htmlspecialchars($active_zone_name) ?>">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Location / Shelf</label>
                <select name="location_code" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-body); color: var(--text-main);">
                    <?php if (!empty($zone_locs)): ?>
                        <?php foreach ($zone_locs as $zl): ?>
                            <option value="<?= htmlspecialchars($zl) ?>"><?= htmlspecialchars($zl) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Sector</label>
                <select name="sector" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-body); color: var(--text-main);">
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= htmlspecialchars($s['name']) ?>" <?= $selected_sector === $s['name'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Select Photo</label>
                <input type="file" name="photo" accept="image/*" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-body);">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Layer / Category</label>
                <select name="category" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-body); color: var(--text-main);">
                    <option value="Layer 1 (Bottom)">Layer 1 (Bottom)</option>
                    <option value="Layer 2">Layer 2</option>
                    <option value="Layer 3">Layer 3</option>
                    <option value="Layer 4">Layer 4</option>
                    <option value="Layer 5 (Top)">Layer 5 (Top)</option>
                    <option value="Row View">Row / Overall View</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn-action" style="flex: 2; padding: 10px; background: var(--accent-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Upload Photo</button>
                <button type="button" onclick="document.getElementById('zone-upload-photo-modal').style.display='none'" class="btn-action" style="flex: 1; padding: 10px; background: var(--text-dim); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Rename Working Zone Modal -->
<div id="rename-working-zone-modal" class="modal-overlay no-print"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;"
    onclick="if(event.target===this) closeRenameWorkingZoneModal()">
    <div
        style="background:white; border-radius:24px; width:95%; max-width:450px; padding:35px; box-shadow:var(--shadow-lg); position:relative;">
        <form method="POST" id="delete-working-zone-form"
            onsubmit="return confirm('CRITICAL ACTION: This will PERMANENTLY DELETE THIS WORKING ZONE AND ALL LOCATIONS AND ITEMS inside it. This cannot be undone. Proceed?');">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="delete_working_zone">
            <input type="hidden" name="zone_name" id="delete-working-zone-name">
            <button type="submit" class="btn-danger-icon" title="Delete Working Zone"
                style="position:absolute; top:20px; right:20px; background:#fee2e2; color:#ef4444; border:1px solid #fecaca; border-radius:10px; cursor:pointer; font-size:0.85rem; padding:6px 12px; font-weight:700; display:flex; align-items:center; gap:5px;">
                🗑️ <span>Delete</span>
            </button>
        </form>

        <h2 style="font-weight:900; margin-bottom:10px; font-size:1.25rem;">📁 Manage Working Zone</h2>
        <p style="font-size:0.85rem; color:#64748b; margin-bottom:25px;">Update the name of this working zone.</p>

        <form method="POST" onsubmit="submitRenameWorkingZoneAjax(event)">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="rename_working_zone">
            <input type="hidden" name="old_zone_name" id="rename-old-zone-name">

            <div class="form-group" style="margin-bottom:30px;">
                <label for="rename-new-zone-name"
                    style="display:block; font-size:0.7rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#94a3b8;">Working Zone Name</label>
                <input type="text" name="new_zone_name" id="rename-new-zone-name" required
                    style="width:100%; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:800; font-size:1rem;">
            </div>

            <div style="display:flex; gap:12px;">
                <button type="button" onclick="closeRenameWorkingZoneModal()"
                    style="flex:1; height:48px; border-radius:14px; border:1px solid #ddd; background:none; font-weight:800; cursor:pointer; color:#64748b;">Cancel</button>
                <button type="submit"
                    style="flex:1; height:48px; border-radius:14px; border:none; background:var(--text-main); color:white; font-weight:800; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">Update Zone</button>
            </div>
        </form>
    </div>
</div>

<!-- Warehouse Inventory Control Modal -->
<?php include __DIR__ . '/inventory_modal.php'; ?>

