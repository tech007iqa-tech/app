<?php
/**
 * Top Horizontal Summary Banner Card Partial
 * Displays client company badges, total units counter, and action buttons.
 */
?>
<!-- Top Horizontal Summary Banner Card -->
<header class="order-summary-banner card">
    <div class="banner-left">
        <h2 id="batch-builder-top">Order Batch Builder</h2>
        <div class="customer-info-badges">
            <?php if ($customer_info): ?>
                <?php if (!empty(trim($customer_info['company_name'] ?? ''))): ?>
                    <span class="info-badge company">🏢 <?= htmlspecialchars($customer_info['company_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty(trim($customer_info['contact_name'] ?? ''))): ?>
                    <span class="info-badge contact">👤 <?= htmlspecialchars($customer_info['contact_name']) ?></span>
                <?php endif; ?>
            <?php endif; ?>
            <span class="info-badge order-id">📦 ID: <?= htmlspecialchars($current_order) ?></span>
        </div>
    </div>
    <div class="banner-right">
        <div class="total-units-container">
            <span class="label">Total Units:</span>
            <span class="value counter" id="sidebar-total-qty"><?= $total_units ?></span>
        </div>
        <div class="banner-actions">
            <button type="button" class="btn-repeat"
                onclick="openImportModal('<?= htmlspecialchars($current_customer) ?>', '<?= htmlspecialchars($current_order) ?>')"
                title="Import from Clipboard">📋 Import Bulk</button>
            <a href="checkout.php?customer_id=<?= urlencode($current_customer) ?>&order_id=<?= urlencode($current_order) ?>"
                class="btn-finalize">
                Finalize & Checkout →
            </a>
        </div>
    </div>
</header>
