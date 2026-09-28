<?php
// tender_login.php
// Dedicated Staff & Tender Authentication Portal for IQA Metal Store
require_once __DIR__ . '/core/StoreAuth.php';
require_once __DIR__ . '/core/Tender.php';

StoreAuth::initSession();

$returnUrl = $_GET['return_url'] ?? $_POST['return_url'] ?? 'index.php';
// Sanitize return URL to prevent open redirects
if (!preg_match('~^[a-zA-Z0-9_\-\./\?=&%#]+$~', $returnUrl) || str_starts_with($returnUrl, '//') || str_contains($returnUrl, '://')) {
    $returnUrl = 'index.php';
}

// Redirect if already logged in as Tender
if (Tender::isLoggedIn()) {
    header("Location: " . $returnUrl);
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usernameOrEmail = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = StoreAuth::login($usernameOrEmail, $password, 'Tender');

    if ($result['success']) {
        header("Location: " . $returnUrl);
        exit;
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Tender Portal | IQA Metal</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="auth-body tender-auth-body">

    <div class="auth-card-wrapper">
        <div class="auth-header-brand">
            <a href="index.php" class="logo-brand" title="Back to storefront">
                <div class="logos">
                    <span class="logos-title"><span>IQA</span> Metal</span>
                    <small class="logos-tagline">Warehouse Hardware &amp; Electronics</small>
                </div>
            </a>
        </div>

        <div class="auth-card tender-card">
            <div class="auth-card-top">
                <div class="auth-badge-tender">
                    <span class="auth-badge-icon">🏪</span>
                    <span>Tender Operations Portal</span>
                </div>
                <h1 class="auth-title">Store Tender Access</h1>
                <p class="auth-subtitle">
                    Privileged operator access for warehouse catalog synchronization, retail pricing, hardware manifestation, and customer preview.
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert auth-alert-error" role="alert">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-msg"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="tender_login.php" class="auth-form" autocomplete="on">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">

                <div class="form-group">
                    <label for="tenderUsername" class="form-label">Tender Operator Username / Email</label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" id="tenderUsername" name="username" class="form-input" 
                               placeholder="e.g. tender or admin" required autofocus 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="tenderPassword" class="form-label">Access Passcode</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="tenderPassword" name="password" class="form-input" 
                               placeholder="••••••••••••" required>
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn tender-submit-btn">
                    <span>Unlock Tender Privileges</span>
                    <span class="btn-arrow">&rarr;</span>
                </button>
            </form>

            <div class="auth-dev-callout">
                <div class="dev-callout-header">
                    <span class="dev-callout-icon">💡</span>
                    <strong>Default Tender Credentials</strong>
                </div>
                <div class="dev-callout-body">
                    <div class="dev-cred-row">
                        <span>Staff Tender:</span>
                        <code>tender</code> / <code>iqametal123</code>
                        <button type="button" class="dev-autofill-btn" onclick="fillTenderCreds('tender', 'iqametal123')">Fill</button>
                    </div>
                    <div class="dev-cred-row">
                        <span>Administrator:</span>
                        <code>admin</code> / <code>admin123</code>
                        <button type="button" class="dev-autofill-btn" onclick="fillTenderCreds('admin', 'admin123')">Fill</button>
                    </div>
                </div>
            </div>

            <div class="auth-footer-links">
                <a href="login.php" class="auth-switch-link">
                    👤 Shopper or Customer? Go to Customer Login &rarr;
                </a>
                <a href="index.php" class="auth-return-link">
                    &larr; Return to Public Storefront
                </a>
            </div>
        </div>
    </div>

    <script>
    function fillTenderCreds(user, pass) {
        document.getElementById('tenderUsername').value = user;
        document.getElementById('tenderPassword').value = pass;
    }
    </script>
</body>
</html>
