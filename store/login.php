<?php
// login.php
// Dedicated Customer & Shopper Authentication for IQA Metal Store
require_once __DIR__ . '/core/StoreAuth.php';

StoreAuth::initSession();

$returnUrl = $_GET['return_url'] ?? $_POST['return_url'] ?? '';
// Sanitize return URL
if (!empty($returnUrl) && (!preg_match('~^[a-zA-Z0-9_\-\./\?=&%#]+$~', $returnUrl) || str_starts_with($returnUrl, '//') || str_contains($returnUrl, '://'))) {
    $returnUrl = '';
}

// Redirect if already logged in
if (!StoreAuth::isGuest()) {
    if (StoreAuth::isTender()) {
        header("Location: " . ($returnUrl ?: 'index.php'));
        exit;
    } else {
        header("Location: " . ($returnUrl ?: 'account.php'));
        exit;
    }
}

$error = null;
$successMsg = !empty($_GET['registered']) ? 'Account created successfully! Please sign in.' : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usernameOrEmail = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = StoreAuth::login($usernameOrEmail, $password);

    if ($result['success']) {
        $dest = $returnUrl;
        if (empty($dest)) {
            $dest = in_array($result['user']['role'], ['Tender', 'Admin']) ? 'index.php' : 'account.php';
        }
        header("Location: " . $dest);
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
    <title>Sign In | IQA Metal</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="auth-body">

    <div class="auth-card-wrapper">
        <div class="auth-header-brand">
            <a href="index.php" class="logo-brand" title="Back to storefront">
                <div class="logos">
                    <span class="logos-title"><span>IQA</span> Metal</span>
                    <small class="logos-tagline">Warehouse Hardware &amp; Electronics</small>
                </div>
            </a>
        </div>

        <div class="auth-card">
            <div class="auth-card-top">
                <div class="auth-badge-customer">
                    <span class="auth-badge-icon">🛍️</span>
                    <span>Customer Account</span>
                </div>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">
                    Sign in to track orders, save hardware specs, and checkout faster.
                </p>
            </div>

            <?php if (!empty($successMsg)): ?>
                <div class="auth-alert auth-alert-success" role="alert">
                    <span class="alert-icon">✓</span>
                    <div class="alert-msg"><?= htmlspecialchars($successMsg) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="auth-alert auth-alert-error" role="alert">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-msg"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form" autocomplete="on">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">

                <div class="form-group">
                    <label for="custUsername" class="form-label">Username or Email</label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" id="custUsername" name="username" class="form-input" 
                               placeholder="e.g. customer or user@domain.com" required autofocus 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="custPassword" class="form-label">Password</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="custPassword" name="password" class="form-input" 
                               placeholder="••••••••••••" required>
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Sign In to Account</span>
                    <span class="btn-arrow">&rarr;</span>
                </button>
            </form>

            <div class="auth-dev-callout customer-callout">
                <div class="dev-callout-header">
                    <span class="dev-callout-icon">💡</span>
                    <strong>Demo Customer Credentials</strong>
                </div>
                <div class="dev-callout-body">
                    <div class="dev-cred-row">
                        <span>Customer Account:</span>
                        <code>customer</code> / <code>customer123</code>
                        <button type="button" class="dev-autofill-btn" onclick="fillCustCreds('customer', 'customer123')">Fill</button>
                    </div>
                </div>
            </div>

            <div class="auth-card-divider">
                <span>New to IQA Metal?</span>
            </div>

            <div class="auth-register-cta">
                <a href="register.php<?= !empty($returnUrl) ? ('?return_url=' . urlencode($returnUrl)) : '' ?>" class="auth-secondary-btn">
                    Create a Free Customer Account
                </a>
            </div>

            <div class="auth-footer-links">
                <a href="tender_login.php" class="auth-tender-portal-link" title="Warehouse & Store Tender login">
                    🏪 Warehouse Operator or Staff? Sign into Tender Portal &rarr;
                </a>
                <a href="index.php" class="auth-return-link">
                    &larr; Return to Browse Catalog
                </a>
            </div>
        </div>
    </div>

    <script>
    function fillCustCreds(user, pass) {
        document.getElementById('custUsername').value = user;
        document.getElementById('custPassword').value = pass;
    }
    </script>
</body>
</html>
