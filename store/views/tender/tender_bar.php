<?php
// views/tender/tender_bar.php
// Privileged toolbar displayed at the top of the screen for logged-in Store Tenders
require_once __DIR__ . '/../../core/Tender.php';

if (!Tender::isLoggedIn()) {
    return;
}

$tender = Tender::current();
$isPreview = Tender::isCustomerPreview();
$stockCount = isset($inventory) ? $inventory->getWarehouseStockCount() : 0;
?>

<div class="tender-top-bar <?= $isPreview ? 'preview-mode' : '' ?>">
    <div class="tender-bar-left">
        <span class="tender-identity">
            <span class="tender-badge">🏪 TENDER</span>
            <strong><?= htmlspecialchars($tender['display_name']) ?></strong>
            <span class="tender-role">(<?= htmlspecialchars($tender['role']) ?>)</span>
        </span>
        
        <?php if ($isPreview): ?>
            <span class="tender-preview-notice">
                👁️ <strong>Customer Preview Active:</strong> Inline edit forms and staff tools are hidden.
            </span>
        <?php endif; ?>
    </div>

    <div class="tender-bar-right">
        <?php if ($isPreview): ?>
            <a href="?preview=0" class="tender-btn tender-btn-primary" title="Exit Preview and return to Tender editing mode">
                ✏️ Return to Tender Mode
            </a>
        <?php else: ?>
            <button type="button" class="tender-btn tender-btn-stock" onclick="openWarehouseModal()" title="Browse and publish physical warehouse stock">
                📦 Warehouse Stock <span class="tender-stock-pill" id="whNavBadge"><?= $stockCount ?></span>
            </button>
            <a href="?preview=1" class="tender-btn tender-btn-secondary" title="View the store as public customers see it">
                👁️ Customer Preview
            </a>
        <?php endif; ?>

        <a href="<?= Tender::logoutUrl() ?>" class="tender-btn tender-btn-signout" title="Sign out of Tender privileges">
            🚪 Sign Out
        </a>
    </div>
</div>
