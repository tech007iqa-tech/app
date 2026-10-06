<!-- STEP 4: FINAL REVIEW -->
<div class="step-content" id="step-4">
    <h2 style="font-size: 1.25rem; margin-bottom: 6px;">📋 Review &amp; Launch</h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Review your
        operational setup before initializing the warehouse ecosystem.</p>

    <div
        style="background: rgba(15, 23, 42, 0.6); border: 1px solid var(--card-border); border-radius: 14px; padding: 1.5rem; display: grid; gap: 12px; font-size: 0.9rem;">
        <div
            style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
            <span style="color: var(--text-muted);">Company Name:</span>
            <strong id="rev-company-name">IQA Metal</strong>
        </div>
        <div
            style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
            <span style="color: var(--text-muted);">System Title:</span>
            <strong id="rev-system-title">IQA Metal Warehouse Systems</strong>
        </div>
        <div
            style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
            <span style="color: var(--text-muted);">Website URL:</span>
            <strong id="rev-company-url">https://iqametal.com</strong>
        </div>
        <div
            style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
            <span style="color: var(--text-muted);">Administrator:</span>
            <strong id="rev-admin-user">admin</strong>
        </div>
        <div
            style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
            <span style="color: var(--text-muted);">Auth Security Mode:</span>
            <span id="rev-auth-mode"><strong style="color: #38bdf8;">🔑 Perfect Paper Passwords</strong></span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: var(--text-muted);">Database Isolation:</span>
            <span style="color: #10b981; font-weight: 700;">✓ Active (Protected outside HTTP)</span>
        </div>
    </div>

    <?php if ($system_protected): ?>
        <div
            style="margin-top: 1.5rem; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 14px; padding: 1.25rem;">
            <label for="current_admin_password"
                style="display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; font-weight: 700; color: #fca5a5; margin-bottom: 6px;">
                <span>🔒 Authorize Changes: Current Admin Password *</span>
                <span style="font-size: 0.72rem; color: #f87171; font-weight: 600;">Mandatory Confirmation</span>
            </label>
            <p style="font-size: 0.78rem; color: #94a3b8; margin-bottom: 12px; line-height: 1.4;">
                To safeguard live production databases against unauthorized modifications, confirm your
                <strong>current administrator password</strong> (or active PPP passcode) to commit changes.
            </p>
            <div style="position: relative;">
                <input type="password" id="current_admin_password" name="current_admin_password"
                    placeholder="Enter current admin password to commit changes..." required
                    style="padding-right: 44px; font-size: 0.9rem;">
                <button type="button" onclick="togglePassVisibility('current_admin_password')"
                    style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; padding: 4px;"
                    title="Toggle visibility">
                    👁️
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>
