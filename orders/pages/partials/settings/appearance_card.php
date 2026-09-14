<?php
/**
 * Settings Card 0: Appearance Partial
 * Allows users to toggle dark/light theme and sign out.
 */
?>
<!-- 0. APPEARANCE CARD -->
<div class="settings-card">
    <div class="settings-header">
        <a href="core/logout.php" style="float:right;text-decoration: none; background: #fef2f2; color: #991b1b; padding: 10px 16px; border-radius: 10px; font-size: 0.8rem; font-weight: 800; border: 1px solid #fee2e2;">
            🚪 Sign Out
        </a>
        <h1>Appearance</h1>
        <p class="subtitle">Customize the look and feel of the application.</p>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
        <div>
            <strong style="display: block; color: var(--text-main); margin-bottom: 4px;">Dark Mode</strong>
            <span style="font-size: 0.85rem; color: var(--text-secondary);">Toggle between light and dark themes.</span>
        </div>
        <?= UI::theme_toggle() ?>
    </div>
</div>
