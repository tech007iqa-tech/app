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

<!-- Universal Camera & Photo Uploader Modal Component -->
<?php include __DIR__ . '/camera_modal.php'; ?>

<?php if (!empty($active_zone_name)): ?>
<!-- Zone Photos Modal -->
<div id="zone-photos-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 800px; max-height: 85vh; padding: 1.5rem; animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: #ffffff; color: #1e293b; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); display: flex; flex-direction: column; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-shrink: 0;">
            <h3 style="margin: 0; font-size: 1.25rem;">📸 Photos for Zone: <?= htmlspecialchars($active_zone_name) ?></h3>
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="CameraUploader.open({ locationCode: '<?= !empty($zone_locs) ? htmlspecialchars($zone_locs[0], ENT_QUOTES, 'UTF-8') : '' ?>', sector: '<?= htmlspecialchars($selected_sector ?? '', ENT_QUOTES, 'UTF-8') ?>', availableLocations: <?= htmlspecialchars(json_encode(array_values($zone_locs ?? [])), ENT_QUOTES, 'UTF-8') ?>, onSuccess: () => window.location.reload() })" class="btn-action" style="background: var(--accent-color); color: white; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 0.85rem; font-weight: 600;">📷 Capture / Upload Photo</button>
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
                                <button type="button" onclick="deleteLocationPhotoAjax(<?= $photo['id'] ?>, this)" style="background: none; border: none; padding: 0; cursor: pointer; font-size: 0.85rem;" title="Delete Photo">🗑️</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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

