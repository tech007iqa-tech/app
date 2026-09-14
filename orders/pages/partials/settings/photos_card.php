<?php
/**
 * Settings Card 5: Location Photography Storage & Backups Partial (Admin Only)
 * Configures raw photo archive directory on disk and manages .tar photo backup export/restore.
 */
if ($_SESSION['username'] !== 'admin') return;

$currentArchivePath = '';
try {
    $conn_w = Database::warehouse();
    $stmt = $conn_w->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute(['archive_photos_path']);
    $currentArchivePath = $stmt->fetchColumn();
} catch (Exception $e) {}

if (empty($currentArchivePath)) {
    $currentArchivePath = dirname(__DIR__, 2) . '/assets/location_photos/archive/';
}
?>
<!-- PHOTO STORAGE & BACKUP CARD (ADMIN ONLY) -->
<div class="settings-card" style="border-top: 4px solid var(--accent-color);">
    <div class="settings-header">
        <h1 style="color: var(--accent-color);">📷 Location Photography Settings</h1>
        <p class="subtitle">Configure raw photo storage and manage database photo backups.</p>
    </div>

    <form method="POST" style="margin-bottom: 30px;">
        <?= UI::csrf_field() ?>
        <input type="hidden" name="action" value="update_archive_path">

        <div class="form-group" style="margin-bottom: 15px;">
            <label for="archive_photos_path" style="display:block; font-size:0.75rem; font-weight:800; text-transform:uppercase; margin-bottom:6px; color:#64748b;">Raw Photo Archive Directory</label>
            <div style="display: flex; gap: 10px;">
                <input type="text" name="archive_photos_path" id="archive_photos_path" value="<?= htmlspecialchars($currentArchivePath) ?>" required
                    style="flex: 1; height:46px; border-radius:12px; border:1px solid #ddd; padding:0 15px; font-weight:600; font-size:0.9rem; background: var(--bg-body); color: var(--text-main);">
                <button type="button" onclick="openDirPicker()" style="padding: 0 15px; height: 46px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); font-weight: 700; cursor: pointer; white-space: nowrap;">🔍 Browse...</button>
            </div>
            <small style="font-size: 0.75rem; color: var(--text-dim); display: block; margin-top: 6px;">Specify a physical path to save raw photos (e.g. a spinning drive like <code>D:/warehouse_archive_photos/</code>).</small>
        </div>

        <button type="submit" class="btn-main" style="width: 100%; padding: 14px; border-radius: 12px; background: var(--accent-color); color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
            💾 Save Storage Path
        </button>
    </form>

    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">

    <div style="margin-bottom: 30px;">
        <h3 style="font-size: 0.95rem; color: var(--text-main); margin-bottom: 8px;">Export Photos Backup</h3>
        <p style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4; margin-bottom: 15px;">Download a standard <code>.tar</code> backup containing all raw location photos and database links.</p>
        <form method="POST">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="export_photos_backup">
            <button type="submit" class="btn-main" style="width: 100%; padding: 14px; border-radius: 12px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                📦 Create & Download .tar Backup
            </button>
        </form>
    </div>

    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">

    <div>
        <h3 style="font-size: 0.95rem; color: var(--text-main); margin-bottom: 8px;">Restore / Import Photos Backup</h3>
        <p style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4; margin-bottom: 15px;">Upload a previously downloaded <code>.tar</code> backup file to populate the photo database and copy files. Duplicate raw files will be auto-renamed gracefully.</p>
        <form method="POST" enctype="multipart/form-data">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="import_photos_backup">

            <div class="form-group" style="margin-bottom: 15px;">
                <input type="file" name="backup_tar" accept=".tar" required
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-body); color: var(--text-main);">
            </div>

            <button type="submit" class="btn-main" style="width: 100%; padding: 14px; border-radius: 12px; background: #059669; color: white; border: none; font-weight: 800; cursor: pointer;">
                📥 Upload & Restore .tar Backup
            </button>
        </form>
    </div>
</div>
