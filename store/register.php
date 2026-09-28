<?php
// register.php
// Dedicated Customer Registration for IQA Metal Store
require_once __DIR__ . '/core/StoreAuth.php';

StoreAuth::initSession();

$returnUrl = $_GET['return_url'] ?? $_POST['return_url'] ?? '';
// Sanitize return URL
if (!empty($returnUrl) && (!preg_match('~^[a-zA-Z0-9_\-\./\?=&%#]+$~', $returnUrl) || str_starts_with($returnUrl, '//') || str_contains($returnUrl, '://'))) {
    $returnUrl = '';
}

// Redirect if already logged in
if (!StoreAuth::isGuest()) {
    header("Location: " . ($returnUrl ?: 'account.php'));
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $result = StoreAuth::register($_POST);

    if ($result['success']) {
        $dest = $returnUrl ?: 'account.php';
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
    <title>Create Account | IQA Metal</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/store.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="auth-body">

    <div class="auth-card-wrapper auth-card-wide">
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
                    <span class="auth-badge-icon">✨</span>
                    <span>New Customer</span>
                </div>
                <h1 class="auth-title">Create Your Account</h1>
                <p class="auth-subtitle">
                    Register to save shipping details, view acquired hardware manifests, and speed up checkout.
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert auth-alert-error" role="alert">
                    <span class="alert-icon">⚠️</span>
                    <div class="alert-msg"><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="auth-form" autocomplete="on">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnUrl) ?>">

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="regUsername" class="form-label">Username <span class="required">*</span></label>
                        <input type="text" id="regUsername" name="username" class="form-input" 
                               placeholder="e.g. jdoe" required autofocus
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="regEmail" class="form-label">Email Address <span class="required">*</span></label>
                        <input type="email" id="regEmail" name="email" class="form-input" 
                               placeholder="e.g. jdoe@example.com" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="regDisplayName" class="form-label">Full Name / Display Name</label>
                        <input type="text" id="regDisplayName" name="display_name" class="form-input" 
                               placeholder="e.g. John Doe"
                               value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="regPhone" class="form-label">Phone Number (Optional)</label>
                        <input type="tel" id="regPhone" name="phone" class="form-input" 
                               placeholder="(555) 000-0000"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="regPassword" class="form-label">Password <span class="required">*</span></label>
                    <input type="password" id="regPassword" name="password" class="form-input" 
                           placeholder="At least 6 characters" required minlength="6">
                </div>

                <div class="form-section-divider">
                    <span>Shipping Address (Optional)</span>
                </div>

                <div class="form-group">
                    <label for="regAddr1" class="form-label">Address Line 1</label>
                    <input type="text" id="regAddr1" name="address_line1" class="form-input" 
                           placeholder="Street address or P.O. Box"
                           value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>">
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label for="regCity" class="form-label">City</label>
                        <input type="text" id="regCity" name="city" class="form-input" placeholder="City"
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="regState" class="form-label">State</label>
                        <input type="text" id="regState" name="state" class="form-input" placeholder="State"
                               value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="regZip" class="form-label">ZIP Code</label>
                        <input type="text" id="regZip" name="zip" class="form-input" placeholder="ZIP"
                               value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn">
                    <span>Complete Registration</span>
                    <span class="btn-arrow">&rarr;</span>
                </button>
            </form>

            <div class="auth-footer-links">
                <a href="login.php<?= !empty($returnUrl) ? ('?return_url=' . urlencode($returnUrl)) : '' ?>" class="auth-switch-link">
                    Already have an account? Sign in here &rarr;
                </a>
                <a href="index.php" class="auth-return-link">
                    &larr; Return to Browse Catalog
                </a>
            </div>
        </div>
    </div>
</body>
</html>
