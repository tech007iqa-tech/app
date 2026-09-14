<?php
/**
 * Settings Page (Main Orchestrator)
 * Modularized view coordinating Appearance, Sales Importer, PPP Security, Signature, Staff Management, Photo Storage, and Maintenance.
 */

// 1. Process Actions (Authentication, PPP crypto, staff roles, and maintenance endpoints)
require_once __DIR__ . '/partials/settings/actions.php';
?>

<!-- Settings State Hydration Payload -->
<script id="settings-state" type="application/json">
<?= json_encode([
    'seq_key'         => $seq_key,
    'saved_row_index' => $saved_row_index,
    'saved_pass_len'  => $saved_pass_len,
    'is_forced'       => $is_forced,
    'username'        => $username
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<div class="settings-page-wrapper" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 40px; padding-bottom: 60px;">
    <style>
        .settings-card {
            background: white;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-top: 20px;
        }
        .settings-header { margin-bottom: 30px; }
        .settings-header h1 { font-size: 1.4rem; margin-bottom: 8px; }
        .status-msg { padding: 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; margin-bottom: 20px; text-align: center; }
        .msg-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .msg-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .user-list { list-style: none; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 20px; }
        .user-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-radius: 8px; background: #f8fafc; margin-bottom: 8px; }
        .user-name { font-weight: 700; font-size: 0.9rem; color: var(--text-main); }
        .btn-delete-small { background: #fee2e2; color: #b91c1c; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 800; }
    </style>

    <!-- Global System Feedback -->
    <?php if ($is_forced): ?>
        <div class="status-msg msg-error" style="width:100%; max-width:500px; margin-top:20px; background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5;">
            ⚠️ <strong>Security Warning:</strong> You are using default credentials. You must change your password to secure the system.
        </div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="status-msg msg-success" style="width:100%; max-width:500px; margin-top:20px;"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="status-msg msg-error" style="width:100%; max-width:500px; margin-top:20px;"><?= $error ?></div>
    <?php endif; ?>

    <!-- 2. Settings Feature Cards -->
    <?php include __DIR__ . '/partials/settings/appearance_card.php'; ?>
    <?php include __DIR__ . '/partials/settings/importer_card.php'; ?>
    <?php include __DIR__ . '/partials/settings/security_card.php'; ?>
    <?php include __DIR__ . '/partials/settings/signature_card.php'; ?>
    <?php include __DIR__ . '/partials/settings/staff_card.php'; ?>
    <?php include __DIR__ . '/partials/settings/photos_card.php'; ?>
    <?php include __DIR__ . '/partials/settings/maintenance_card.php'; ?>
</div>
