<?php
/**
 * 404 Not Found Page
 * Premium design with "Safety Green" accents.
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Logic for the Dynamic "Back" Link
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$backLink = 'index.php';

if (!empty($referer) && strpos($referer, '404.php') === false) {
    $backLink = $referer;
}
?>

<div class="panel text-center" style="padding: 100px 20px; min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center;">

    <div style="font-size: 8rem; font-weight: 800; color: var(--accent-color); line-height: 1; margin-bottom: 20px; opacity: 0.2;">
        404
    </div>

    <h1 style="font-size: 2.5rem; margin-bottom: 15px; color: var(--text-main);">Oops! Page Missing</h1>

    <p style="font-size: 1.1rem; color: var(--text-secondary); max-width: 500px; margin: 0 auto 40px; line-height: 1.6;">
        It looks like the link you followed is broken or the record has been moved.
        Don't worry, even the best hardware occasionally fails.
    </p>

    <div style="display: flex; gap: 15px; justify-content: center;">
        <a href="<?= htmlspecialchars($backLink) ?>" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem; border-radius: 50px; box-shadow: 0 4px 15px rgba(140, 198, 63, 0.2);">
            ⬅ Return to Last Page
        </a>

        <a href="labels.php" class="btn" style="padding: 15px 40px; font-size: 1.1rem; border-radius: 50px; background: var(--bg-panel); border: 1px solid var(--border-color); color: var(--text-main);">
            📦 Inventory
        </a>
    </div>

    <div style="margin-top: 60px; color: var(--text-secondary); font-size: 0.9rem;">
        If you believe this is a system error, please contact your administrator.
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
