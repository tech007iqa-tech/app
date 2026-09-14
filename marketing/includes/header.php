<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= h(Security::getToken()) ?>">
    <title><?= h(APP_NAME); ?> - Management Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
    <?php if (isset($_SESSION['notify'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const notifyPayload = <?= json_encode($_SESSION['notify'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                if (window.notify && notifyPayload) {
                    notify(notifyPayload.message || '', notifyPayload.type || 'info', notifyPayload.title || '');
                }
            });
        </script>
        <?php unset($_SESSION['notify']); ?>
    <?php endif; ?>
    <?php $active_page = $_GET['page'] ?? 'dashboard'; ?>
    <nav class="main-nav">
        <div class="nav-container">
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="../index.php" title="Return to Main Portal" style="text-decoration: none; color: var(--text-dim); font-size: 0.85rem; padding: 4px 8px; border: 1px solid var(--border-color); border-radius: 6px; background: rgba(255,255,255,0.03);">← Portal</a>
                <a href="index.php" class="brand"><?= h(APP_NAME); ?></a>
            </div>
            <ul class="nav-links">
                <li><a href="?page=dashboard" class="<?= $active_page === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="?page=leads" class="<?= $active_page === 'leads' ? 'active' : '' ?>">Leads</a></li>
                <li><a href="?page=model_templates" class="<?= $active_page === 'model_templates' ? 'active' : '' ?>">Templates</a></li>
                <li><a href="?page=ad_generator" class="<?= $active_page === 'ad_generator' ? 'active' : '' ?>">Ad Generator</a></li>
                <li><a href="?page=campaigns" class="<?= $active_page === 'campaigns' ? 'active' : '' ?>">Campaigns</a></li>
                <li><a href="?page=photo_bucket" class="<?= $active_page === 'photo_bucket' ? 'active' : '' ?>">Photo Bucket</a></li>
                <li><a href="?page=reports" class="<?= $active_page === 'reports' ? 'active' : '' ?>">Reports</a></li>
                <li><a href="?page=docs" class="<?= $active_page === 'docs' ? 'active' : '' ?>">Docs</a></li>
            </ul>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 0.8rem; color: var(--text-dim); font-weight: 600;">👤 <?= h($_SESSION['username'] ?? 'User') ?></span>
                <a href="../orders/core/logout.php" style="text-decoration: none; color: #ef4444; font-size: 0.8rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.08);">Sign Out</a>
            </div>
        </div>
    </nav>


