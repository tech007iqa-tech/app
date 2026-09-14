<?php
/**
 * Settings Card 6 & 7: System Maintenance, Storage Health, Audit Logs & Directory Picker Modal (Admin Only)
 */
if ($_SESSION['username'] !== 'admin') return;
?>
<!-- 4. SYSTEM MAINTENANCE CARD (ADMIN ONLY) -->
<div class="settings-card" style="border-top: 4px solid #ef4444;">
    <div class="settings-header">
        <h1 style="color: #991b1b;">System Maintenance</h1>
        <p class="subtitle">Perform administrative cleanup tasks to keep the database tidy.</p>
    </div>

    <form method="POST" onsubmit="return confirm('This will permanently delete all customers who have never placed an order. Are you sure?');">
        <?= UI::csrf_field() ?>
        <input type="hidden" name="action" value="cleanup_customers">
        <div style="background: #fef2f2; border: 1px solid #fee2e2; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
            <h3 style="font-size: 0.9rem; color: #991b1b; margin-bottom: 8px;">Purge Inactive Customers</h3>
            <p style="font-size: 0.8rem; color: #7f1d1d; line-height: 1.4;">Identify and remove customer profiles that haven't been assigned to any orders or batches yet.</p>
        </div>
        <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: #ef4444; color: white; border: none; font-weight: 800; cursor: pointer;">
            🗑️ Clean Up 0-Order Customers
        </button>
    </form>

    <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #fecdd3;">
        <h1 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 6px;">Schema Integrity Check</h1>
        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 18px;">Use this if the system was deployed fresh and columns are missing from an existing database. This is safe to run at any time — it only <em>adds</em> what's missing, never deletes data.</p>

        <?php
        $integrity_report = $_SESSION['integrity_report'] ?? null;
        unset($_SESSION['integrity_report']);
        if ($integrity_report): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px 20px; margin-bottom: 18px; font-size: 0.78rem; font-family: monospace; color: #166534; max-height: 180px; overflow-y: auto;">
            <strong style="display:block; margin-bottom: 8px; font-size:0.85rem;">Repair Report</strong>
            <?php foreach ($integrity_report['fixed'] as $t): ?>
                <div style="padding: 2px 0;">✓ <?= htmlspecialchars($t) ?></div>
            <?php endforeach; ?>
            <?php foreach ($integrity_report['errors'] as $e): ?>
                <div style="color:#b91c1c; padding: 2px 0;">✗ <?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="integrity_check">
            <button type="submit" id="btn-integrity-check" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.95rem;">
                🔧 Run Schema Integrity Check
            </button>
            <p style="font-size: 0.75rem; color: #64748b; text-align: center; margin-top: 10px;">Inspects all tables across every database and applies any missing column migrations.</p>
        </form>
    </div>

    <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #fecdd3;">
        <h1 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 15px;">Data Security & Backups</h1>

        <div style="background: #f0f9ff; border: 1px solid #e0f2fe; padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px;">
            <div style="font-size: 2rem;">🛡️</div>
            <div>
                <h3 style="font-size: 0.9rem; color: #0369a1; margin-bottom: 4px;">One-Click System Backup</h3>
                <p style="font-size: 0.8rem; color: #075985; line-height: 1.4;">Download a compressed ZIP archive containing all customers, orders, warehouse inventory, and system logs.</p>
            </div>
            <a href="api/generate_backup.php" class="btn-main" style="background: #0369a1; color: white; padding: 12px 20px; font-size: 0.85rem; white-space: nowrap;">
                Download ZIP
            </a>
        </div>

        <h1 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 15px;">Storage Health</h1>

        <div style="display: grid; gap: 10px; margin-bottom: 25px;">
            <?php
            $dbs = ['customers', 'orders', 'warehouse', 'users', 'calendar'];
            $db_dir = Database::getDbDir();
            foreach ($dbs as $db) {
                $path = $db_dir . "/{$db}.db";
                $size = file_exists($path) ? round(filesize($path) / 1024, 2) . ' KB' : 'Not Created';
                echo "<div style='display:flex; justify-content:space-between; padding:12px 15px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;'>
                        <span style='font-size:0.8rem; font-weight:800; color:var(--text-secondary); text-transform:uppercase;'>{$db}.db</span>
                        <span style='font-size:0.85rem; font-weight:700; color:var(--text-main);'>{$size}</span>
                      </div>";
            }
            ?>
        </div>

        <form method="POST">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="optimize_db">
            <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: #0369a1; color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                ⚡ Optimize System Performance
            </button>
            <p style="font-size: 0.75rem; color: #64748b; text-align: center; margin-top: 12px;">This will re-index databases and reclaim unused disk space.</p>
        </form>
    </div>
</div>

<!-- 5. SYSTEM ACTIVITY LOG (ADMIN ONLY) -->
<div class="settings-card" style="max-width: 800px; width: 95%;">
    <div class="settings-header">
        <h1>System Activity Log</h1>
        <p class="subtitle">A permanent record of sensitive actions performed by staff members.</p>
    </div>

    <div class="audit-log-container" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 12px; background: #fafafa;">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
            <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 1;">
                <tr>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Time</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Staff</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Action</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Target</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = Audit::getRecent(20);
                if (empty($logs)): ?>
                    <tr><td colspan="4" style="padding: 40px; text-align: center; color: #94a3b8;">No activity recorded yet.</td></tr>
                <?php else:
                    foreach($logs as $l):
                        $badge_color = strpos($l['action'], 'DELETE') !== false ? '#ef4444' : '#3b82f6';
                ?>
                    <tr style="border-bottom: 1px solid #eee; background: white;">
                        <td style="padding: 12px; color: #64748b; white-space: nowrap;"><?= date('M d, H:i', strtotime($l['timestamp'])) ?></td>
                        <td style="padding: 12px; font-weight: 700;"><?= htmlspecialchars($l['user_name']) ?></td>
                        <td style="padding: 12px;">
                            <span style="background: <?= $badge_color ?>; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">
                                <?= htmlspecialchars($l['action']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px;">
                            <div style="font-weight: 700;"><?= htmlspecialchars($l['target_id']) ?></div>
                            <div style="font-size: 0.7rem; color: #94a3b8;"><?= htmlspecialchars($l['details']) ?></div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 15px; text-align: center;">The audit log is read-only and cannot be modified by staff.</p>
</div>

<!-- Directory Picker Modal -->
<div id="dir-picker-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 3000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 95%; max-width: 500px; max-height: 80vh; padding: 1.5rem; display: flex; flex-direction: column; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-shrink: 0;">
            <h3 style="margin: 0; font-size: 1.25rem;">📂 Select Archive Directory</h3>
            <button type="button" onclick="closeDirPicker()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dim);">×</button>
        </div>

        <div style="margin-bottom: 15px; font-weight: 700; font-size: 0.85rem; color: var(--text-secondary); word-break: break-all; flex-shrink: 0;">
            Current Path: <span id="dir-picker-current-path" style="color: var(--text-main); font-family: monospace;">-</span>
        </div>

        <div id="dir-picker-list" style="overflow-y: auto; flex-grow: 1; border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; background: var(--bg-body); min-height: 250px;">
            <!-- Populated by JS -->
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem; flex-shrink: 0;">
            <button type="button" onclick="selectCurrentDir()" class="btn-action" style="flex: 2; padding: 10px; background: var(--accent-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Select Current Folder</button>
            <button type="button" onclick="closeDirPicker()" class="btn-action" style="flex: 1; padding: 10px; background: var(--text-dim); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancel</button>
        </div>
    </div>
</div>
