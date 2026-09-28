<?php
// account.php
// Customer & User Account Dashboard for IQA Metal Store
require_once __DIR__ . '/core/StoreAuth.php';
require_once __DIR__ . '/core/Cart.php';

StoreAuth::initSession();

if (StoreAuth::isGuest()) {
    header("Location: login.php?return_url=" . urlencode('account.php'));
    exit;
}

$user = StoreAuth::getCurrentUserDetails();
if (!$user) {
    StoreAuth::logout();
    header("Location: login.php");
    exit;
}

$cart = new Cart();
$cartCount = $cart->getCount();
$pageTitle = 'My Account';
$activeCategory = 'account';

$msg = null;
$msgType = 'success';

// Handle Profile Updates
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $updated = StoreAuth::updateProfile((int)$user['id'], $_POST);
        if ($updated) {
            $msg = 'Profile details updated successfully!';
            $user = StoreAuth::getCurrentUserDetails(); // Refresh
        } else {
            $msg = 'Failed to update profile. Please try again.';
            $msgType = 'error';
        }
    } elseif ($action === 'change_password') {
        $oldPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if ($newPass !== $confirmPass) {
            $msg = 'New password and confirmation do not match.';
            $msgType = 'error';
        } else {
            $res = StoreAuth::changePassword((int)$user['id'], $oldPass, $newPass);
            if ($res['success']) {
                $msg = 'Password changed successfully!';
            } else {
                $msg = $res['error'] ?? 'Failed to change password.';
                $msgType = 'error';
            }
        }
    }
}

require __DIR__ . '/views/header.php';
?>

<div class="account-container">
    <div class="account-header">
        <div class="account-header-left">
            <div class="account-avatar">
                <span><?= strtoupper(substr($user['display_name'] ?: $user['username'], 0, 1)) ?></span>
            </div>
            <div>
                <h1 class="account-name"><?= htmlspecialchars($user['display_name'] ?: $user['username']) ?></h1>
                <div class="account-meta-row">
                    <span class="account-role-badge <?= strtolower($user['role']) === 'customer' ? 'role-customer' : 'role-tender' ?>">
                        <?= htmlspecialchars($user['role']) ?>
                    </span>
                    <span class="account-email">📧 <?= htmlspecialchars($user['email'] ?: 'No email on file') ?></span>
                    <span class="account-since">Member since <?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <div class="account-header-right">
            <?php if (StoreAuth::isTender()): ?>
                <a href="index.php" class="tender-btn tender-btn-primary" style="text-decoration:none;">
                    🏪 Open Tender Mode
                </a>
            <?php endif; ?>
            <a href="logout.php" class="account-signout-btn">
                🚪 Sign Out
            </a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="auth-alert auth-alert-<?= $msgType === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 1.5rem;">
            <span class="alert-icon"><?= $msgType === 'success' ? '✓' : '⚠️' ?></span>
            <div class="alert-msg"><?= htmlspecialchars($msg) ?></div>
        </div>
    <?php endif; ?>

    <div class="account-grid">
        <!-- Section 1: Profile & Shipping Details -->
        <div class="account-card">
            <div class="account-card-title">
                <span>👤 Personal &amp; Shipping Details</span>
            </div>
            <form method="POST" action="account.php" class="account-form">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="accDisplayName" class="form-label">Display Name</label>
                        <input type="text" id="accDisplayName" name="display_name" class="form-input" 
                               value="<?= htmlspecialchars($user['display_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="accPhone" class="form-label">Phone Number</label>
                        <input type="tel" id="accPhone" name="phone" class="form-input" 
                               value="<?= htmlspecialchars($user['phone']) ?>" placeholder="(555) 000-0000">
                    </div>
                </div>

                <div class="form-section-divider">
                    <span>Shipping Address</span>
                </div>

                <div class="form-group">
                    <label for="accAddr1" class="form-label">Street Address</label>
                    <input type="text" id="accAddr1" name="address_line1" class="form-input" 
                           value="<?= htmlspecialchars($user['address_line1']) ?>" placeholder="Address line 1">
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label for="accCity" class="form-label">City</label>
                        <input type="text" id="accCity" name="city" class="form-input" 
                               value="<?= htmlspecialchars($user['city']) ?>" placeholder="City">
                    </div>
                    <div class="form-group">
                        <label for="accState" class="form-label">State</label>
                        <input type="text" id="accState" name="state" class="form-input" 
                               value="<?= htmlspecialchars($user['state']) ?>" placeholder="State">
                    </div>
                    <div class="form-group">
                        <label for="accZip" class="form-label">ZIP Code</label>
                        <input type="text" id="accZip" name="zip" class="form-input" 
                               value="<?= htmlspecialchars($user['zip']) ?>" placeholder="ZIP">
                    </div>
                </div>

                <button type="submit" class="auth-submit-btn" style="width: auto; padding: 0.65rem 1.5rem; align-self: flex-start;">
                    Save Profile Changes
                </button>
            </form>
        </div>

        <!-- Section 2: Security & Password -->
        <div class="account-card">
            <div class="account-card-title">
                <span>🔒 Security &amp; Password</span>
            </div>
            <form method="POST" action="account.php" class="account-form">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="accOldPass" class="form-label">Current Password</label>
                    <input type="password" id="accOldPass" name="current_password" class="form-input" required placeholder="••••••••••••">
                </div>

                <div class="form-group">
                    <label for="accNewPass" class="form-label">New Password</label>
                    <input type="password" id="accNewPass" name="new_password" class="form-input" required minlength="6" placeholder="Min. 6 characters">
                </div>

                <div class="form-group">
                    <label for="accConfirmPass" class="form-label">Confirm New Password</label>
                    <input type="password" id="accConfirmPass" name="confirm_password" class="form-input" required minlength="6" placeholder="Repeat new password">
                </div>

                <button type="submit" class="auth-submit-btn" style="width: auto; padding: 0.65rem 1.5rem; align-self: flex-start; background: #4b5563;">
                    Update Password
                </button>
            </form>

            <div class="account-quick-links" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--card-border);">
                <div style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.75rem; color: var(--text-color);">Quick Actions</div>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="index.php" class="account-link-pill">🛒 Browse Hardware Catalog</a>
                    <a href="cart.php" class="account-link-pill">🛍️ View Shopping Cart (<?= $cartCount ?>)</a>
                    <?php if (StoreAuth::isTender()): ?>
                        <a href="tender_login.php" class="account-link-pill" style="border-color: var(--primary-color);">🏪 Tender Portal</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>
