<?php
/**
 * Ad Generator Module - The "Manifest Script"
 * Cross-references Warehouse stock with Marketing templates.
 */

// 1. Fetch template names first to use as a filter
$templateModels = [];
try {
    $templateModels = $marketingDb->query("SELECT model_name FROM model_templates")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    error_log("Failed to fetch template models: " . $e->getMessage());
}

// 2. Fetch available models from Labels DB that MATCH templates
$modelsInStock = [];
if ($labelsDb && !empty($templateModels)) {
    try {
        $placeholders = implode(',', array_fill(0, count($templateModels), '?'));
        $stmt = $labelsDb->prepare("
            SELECT brand, model, COUNT(*) as qty
            FROM items
            WHERE status = 'In Warehouse'
            AND model IN ($placeholders)
            GROUP BY brand, model
            ORDER BY qty DESC
        ");
        $stmt->execute($templateModels);
        $modelsInStock = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log("Failed to query stock in labels DB: " . $e->getMessage());
    }
}

// 3. Handle Ad Generation Logic
$selectedModel = isset($_GET['model']) ? trim($_GET['model']) : null;
$tone = isset($_GET['tone']) ? trim($_GET['tone']) : 'manifest'; // manifest, urgency, social
$generatedAd = null;
$matchingTemplate = null;

if ($selectedModel) {
    try {
        // Find template in Marketing DB
        $stmt = $marketingDb->prepare("SELECT * FROM model_templates WHERE model_name = ?");
        $stmt->execute([$selectedModel]);
        $matchingTemplate = $stmt->fetch();

        if ($matchingTemplate) {
            // Calculate QTY for the specific ad
            $qty = 0;
            foreach ($modelsInStock as $m) {
                if ($m['model'] === $selectedModel) {
                    $qty = (int)$m['qty'];
                    break;
                }
            }

            // TONE-BASED GENERATION
            if ($tone === 'urgency') {
                $generatedAd = "⚡ FLASH SALE: {$qty}x " . strtoupper($matchingTemplate['model_name']) . " ⚡\n\n";
                $generatedAd .= "We need to clear space! " . $qty . " units ready for IMMEDIATE palletized shipping.\n\n";
                $generatedAd .= "🔥 KEY SPECS:\n" . UI::format_specs_plain($matchingTemplate['base_specs']) . "\n\n";
                $generatedAd .= "FIRST COME, FIRST SERVED. Reply now for special bulk pricing. 📉";
            } elseif ($tone === 'social') {
                $generatedAd = "✨ Looking for quality " . $matchingTemplate['category'] . "s in bulk? ✨\n\n";
                $generatedAd .= "The " . $matchingTemplate['model_name'] . " is back in stock (" . $qty . " units available).\n\n";
                $generatedAd .= $matchingTemplate['marketing_copy'] . "\n\n";
                $generatedAd .= "#RefurbishedTech #IQA #BulkInventory #ITAD";
            } else {
                // Standard Manifest
                $generatedAd = "🔥 INVENTORY ALERT: " . strtoupper($matchingTemplate['model_name']) . " 🔥\n\n";
                $generatedAd .= "We have just processed a batch of " . $qty . " units, now ready for immediate fulfillment!\n\n";
                $generatedAd .= "📍 SPECIFICATIONS:\n" . UI::format_specs_plain($matchingTemplate['base_specs']) . "\n\n";
                $generatedAd .= "📝 OVERVIEW:\n" . $matchingTemplate['marketing_copy'] . "\n\n";
                $generatedAd .= "DM for pricing and bulk manifest.";
            }

            // De-duplicate audit log per session view
            $logKey = "ad_log_" . md5($selectedModel . $tone . $qty);
            if (!isset($_SESSION[$logKey])) {
                log_marketing_audit($marketingDb, 'AdGenerator', $selectedModel, 'GENERATED', "Generated $tone ad for $selectedModel (Qty: $qty)");
                $_SESSION[$logKey] = true;
            }
        }
    } catch (Throwable $e) {
        error_log("Error generating ad manifest: " . $e->getMessage());
    }
}
?>

<header class="page-header">
    <h1>Inventory-to-Ad Generator</h1>
    <p>Convert real-time warehouse stock into high-performance marketing copy.</p>
</header>

<div class="dashboard-grid">
    <!-- STOCK SELECTOR -->
    <section class="card">
        <h2>1. Select Stock from Warehouse</h2>
        <div class="stock-list">
            <?php if (empty($modelsInStock)): ?>
                <p style="color: var(--text-dim); padding: 1rem 0;">No matching warehouse inventory with templates found.</p>
            <?php else: ?>
                <div class="stock-grid">
                    <?php foreach ($modelsInStock as $stock): ?>
                        <a href="?page=ad_generator&model=<?= urlencode($stock['model']); ?>"
                           class="stock-item <?= ($selectedModel === $stock['model']) ? 'active' : ''; ?>">
                            <div class="stock-qty"><?= (int)$stock['qty']; ?> Units</div>
                            <div class="stock-name"><?= h($stock['brand'] . ' ' . $stock['model']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- AD PREVIEW -->
    <section class="card ad-preview-main">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
            <h2 style="margin: 0;">2. Generated Ad Manifest</h2>
            <?php if ($selectedModel): ?>
            <div class="tone-selector">
                <a href="?page=ad_generator&model=<?= urlencode($selectedModel); ?>&tone=manifest" class="btn-small <?= $tone==='manifest'?'active':''; ?>">Standard</a>
                <a href="?page=ad_generator&model=<?= urlencode($selectedModel); ?>&tone=urgency" class="btn-small <?= $tone==='urgency'?'active':''; ?>">Urgent</a>
                <a href="?page=ad_generator&model=<?= urlencode($selectedModel); ?>&tone=social" class="btn-small <?= $tone==='social'?'active':''; ?>">Social</a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($selectedModel && !$matchingTemplate): ?>
            <div class="alert alert-danger">
                No marketing template found for <strong><?= h($selectedModel); ?></strong>.
                <a href="?page=model_templates&prefill_model=<?= urlencode($selectedModel); ?>" style="color: white; text-decoration: underline;">Create one here</a> to generate an ad.
            </div>
        <?php elseif ($generatedAd): ?>
            <div class="ad-generator-layout">
                <textarea id="adOutput" readonly><?= h($generatedAd); ?></textarea>

                <!-- PHOTO BANK INTEGRATION -->
                <div class="photo-preview-sidebar">
                    <h4 style="font-size: 0.7rem; text-transform: uppercase; margin-bottom: 10px;">Bucket Assets</h4>
                    <?php
                    $bucketPhotos = [];
                    try {
                        $photoStmt = $marketingDb->prepare("SELECT * FROM photos WHERE model_name = ? ORDER BY created_at DESC LIMIT 3");
                        $photoStmt->execute([$selectedModel]);
                        $bucketPhotos = $photoStmt->fetchAll() ?: [];
                    } catch (Throwable $e) {
                        error_log("Failed to fetch bucket photos for ad generator: " . $e->getMessage());
                    }

                    if (empty($bucketPhotos)):
                        for($i=0; $i<3; $i++):
                    ?>
                        <div class="asset-thumb">
                            <span>No Photo</span>
                        </div>
                    <?php
                        endfor;
                    else:
                        foreach($bucketPhotos as $photo):
                            $previewImg = (!empty($photo['thumbnail_path']) && file_exists(__DIR__ . '/../../' . $photo['thumbnail_path']))
                                          ? $photo['thumbnail_path']
                                          : $photo['file_path'];
                    ?>
                        <div class="asset-thumb exists">
                            <img src="<?= h($previewImg); ?>" alt="Stock Photo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
                        </div>
                    <?php
                        endforeach;
                        for($i=count($bucketPhotos); $i<3; $i++):
                    ?>
                         <div class="asset-thumb">
                            <span>Empty Slot</span>
                        </div>
                    <?php
                        endfor;
                    endif;
                    ?>
                    <a href="?page=photo_bucket" style="font-size: 0.7rem; text-align: center; color: var(--accent-primary); text-decoration: none; font-weight: 700; margin-top: 5px;">Go to Bucket →</a>
                </div>
            </div>

            <div class="ad-actions" style="margin-top: 1.5rem;">
                <button type="button" onclick="copyAdToClipboard()" class="btn-action" style="width: 100%;">📋 Copy to Clipboard</button>
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 1rem; text-align: center;">
                    This ad is optimized for <?= h(strtoupper($tone)); ?> outreach.
                </p>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-dim);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">⚡</div>
                <p>Select a model from the left to generate its marketing manifest.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function copyAdToClipboard() {
    const copyText = document.getElementById("adOutput");
    if (!copyText) return;
    copyToClipboard(copyText.value, 'Marketing Manifest');
}
</script>

<style>
.stock-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 0.75rem;
    text-decoration: none;
    color: var(--text-main);
    transition: all 0.2s;
}
.stock-item:hover, .stock-item.active {
    border-color: var(--accent-primary);
    background: var(--accent-tertiary);
}
.stock-qty {
    font-weight: 800;
    font-size: 0.7rem;
    background: var(--accent-primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 2rem;
}
.stock-name {
    font-weight: 700;
    font-size: 0.9rem;
}
.ad-generator-layout {
    display: flex;
    gap: 1.5rem;
}
#adOutput {
    flex: 1;
    height: 380px;
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    color: var(--text-main);
    font-family: inherit;
    font-size: 0.95rem;
    line-height: 1.6;
    resize: none;
}
.photo-preview-sidebar {
    width: 140px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.asset-thumb {
    width: 140px;
    height: 100px;
    background: rgba(0,0,0,0.03);
    border: 1px dashed var(--border-color);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: var(--text-dim);
}
.asset-thumb.exists {
    border: 1px solid var(--border-color);
}
</style>
