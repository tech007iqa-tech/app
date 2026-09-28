<?php
// views/product_card.php
// Modular product card
// Automatically delegates to the Tender edit card if a Tender is currently active!

require_once __DIR__ . '/../core/Tender.php';

if (Tender::isTenderMode()) {
    require __DIR__ . '/tender/product_card_tender.php';
    return;
}

$details = $p['details'] ?? [];
$chips = $details['chips'] ?? [];
$summary = $details['summary'] ?? $p['description'];
?>
<!-- Customer (Shopper) Product Card -->
<article class='card' id='product-card-<?= (int)$p['id'] ?>'>
    <?php if (!empty($p['last_update'])): ?>
        <span class="last-updated">Last update: <?= htmlspecialchars($p['last_update']) ?></span>
    <?php endif; ?>
    
    <figure class='img-wrapper' onclick='openProductModal(<?= (int)$p['id'] ?>)' style='cursor: pointer;' title='Click to view full specifications'>
        <picture>
            <source media='(max-width: 768px)' srcset='<?= htmlspecialchars($p['thumb']) ?>'>
            <img src='<?= htmlspecialchars($p['image']) ?>' alt='<?= htmlspecialchars($p['title']) ?>' class='card-img' width='600' height='600' loading='lazy' onerror="this.onerror=null; this.src='images/placeholder.svg';">
        </picture>
        <span class="img-zoom-badge">🔍 Zoom</span>
    </figure>

    <header>
        <h2 class='card-title' onclick='openProductModal(<?= (int)$p['id'] ?>)' style='cursor: pointer;' title='<?= htmlspecialchars($p['title']) ?>'>
            <?= htmlspecialchars($p['title']) ?>
        </h2>
    </header>

    <?php if (!empty($chips)): ?>
        <div class="card-chips">
            <?php foreach ($chips as $chip): ?>
                <span class="chip-pill" title="<?= htmlspecialchars($chip['text']) ?>">
                    <span class="chip-icon"><?= $chip['icon'] ?></span>
                    <span class="chip-text"><?= htmlspecialchars($chip['text']) ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class='card-desc'><?= htmlspecialchars($summary) ?></p>

    <button type="button" class="view-specs-btn" onclick="openProductModal(<?= (int)$p['id'] ?>)" title="View full hardware manifest, condition notes, and terms">
        <span>📋 Full Specs &amp; Condition</span>
        <span class="view-specs-arrow">&rarr;</span>
    </button>

    <footer class='card-footer'>
        <div class='price'><span>$</span><?= number_format((float)$p['price'], 2) ?></div>
        <form method='POST' action='cart.php' style='margin:0;'>
            <input type='hidden' name='action' value='add'>
            <input type='hidden' name='product_id' value='<?= htmlspecialchars($p['id']) ?>'>
            <button type='submit' class='buy-btn'>Acquire</button>
        </form>
    </footer>

    <!-- Encoded JSON payload for zero-latency modal rendering -->
    <script type="application/json" id="product-json-<?= (int)$p['id'] ?>">
        <?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    </script>
</article>

