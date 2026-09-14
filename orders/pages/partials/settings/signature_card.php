<?php
/**
 * Settings Card 3: Invoice Signature Partial (All Users)
 * Configures the display name printed in the 'Approved By' manifest line.
 */
$sig_stmt = $conn_u->prepare("SELECT display_name FROM users WHERE username = ?");
$sig_stmt->execute([$_SESSION['username']]);
$current_sig = $sig_stmt->fetchColumn() ?: $_SESSION['username'];
?>
<!-- 2. SIGNATURE / INVOICE NAME CARD (ALL USERS) -->
<div class="settings-card">
    <div class="settings-header">
        <h1>Invoice Signature</h1>
        <p class="subtitle">This name appears in the <strong>Approved By</strong> field on all printed manifests.</p>
    </div>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; font-size: 0.9rem; color: var(--text-secondary);">
        Current signature: <strong style="color: var(--text-main); font-size: 1rem;"><?= htmlspecialchars($current_sig) ?></strong>
    </div>

    <form method="POST">
        <?= UI::csrf_field() ?>
        <input type="hidden" name="action" value="update_signature">
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="display_name">Signature / Approved By Name</label>
            <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($current_sig) ?>" placeholder="e.g. John Smith — Operations Manager" required>
        </div>
        <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: var(--accent-color); color: white; border: none; font-weight: 800; cursor: pointer;">
            ✍️ Save Signature
        </button>
    </form>
</div>
