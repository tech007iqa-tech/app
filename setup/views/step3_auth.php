<!-- STEP 3: ADMINISTRATOR ACCOUNT & PPP SECURITY -->
<div class="step-content" id="step-3">
    <h2 style="font-size: 1.25rem; margin-bottom: 6px;">🔐 Administrator Security &amp; Authentication</h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
        Configure master administrator credentials. Use Steve Gibson's <strong>Perfect Paper Passwords
            (PPP)</strong> offline passcard system, fast-track with <strong>default credentials</strong>, or set a custom password.
    </p>

    <!-- Admin Account Identity (Username & Display Name) -->
    <div class="form-grid" style="margin-bottom: 1.5rem;">
        <div class="form-group">
            <label for="admin_user">Admin Username</label>
            <input type="text" id="admin_user" name="admin_user" value="admin" required>
            <span class="input-hint">Username used to log into Order Manager &amp; Tech Center.</span>
        </div>

        <div class="form-group">
            <label for="admin_name">Display Name</label>
            <input type="text" id="admin_name" name="admin_name" value="System Administrator" required>
            <span class="input-hint">Name displayed in headers, manifests, and audit logs.</span>
        </div>
    </div>

    <!-- Hidden Authentication State Inputs -->
    <input type="hidden" name="auth_mode" id="auth_mode_input"
        value="<?= $system_protected ? 'keep_existing' : 'ppp' ?>">
    <input type="hidden" name="ppp_sequence_key" id="ppp_sequence_key_input"
        value="<?= htmlspecialchars($existing_seq_key) ?>">
    <input type="hidden" name="ppp_row_index" id="ppp_row_index_input"
        value="<?= $existing_row_index ?>">
    <input type="hidden" name="ppp_password_len" id="ppp_password_len_input"
        value="<?= $existing_pass_len ?>">
    <input type="hidden" name="selected_passcode" id="selected_passcode_input" value="">

    <!-- Authentication Mode Selector Tabs -->
    <div style="margin-bottom: 1.5rem;">
        <label style="display: block; margin-bottom: 8px;">Choose Authentication Method</label>
        <div class="auth-mode-grid"
            style="<?= $system_protected ? 'grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));' : '' ?>">
            <?php if ($system_protected): ?>
                <div class="auth-mode-card active" id="mode-card-keep"
                    onclick="switchAuthMode('keep_existing')">
                    <div class="auth-mode-icon">🛡️</div>
                    <div class="auth-mode-title">Keep Current Password</div>
                    <div class="auth-mode-badge"
                        style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4);">
                        Preserve (Active)</div>
                    <div class="auth-mode-desc">Retain active admin credentials and passcard without modification.</div>
                </div>
            <?php endif; ?>

            <div class="auth-mode-card <?= !$system_protected ? 'active' : '' ?>" id="mode-card-ppp"
                onclick="switchAuthMode('ppp')">
                <div class="auth-mode-icon">🔑</div>
                <div class="auth-mode-title">Perfect Paper Passwords</div>
                <div class="auth-mode-badge badge-recom">Recommended</div>
                <div class="auth-mode-desc">High-entropy Steve Gibson GRC passcard grid. Offline paper MFA.</div>
            </div>

            <div class="auth-mode-card" id="mode-card-default"
                onclick="switchAuthMode('default_creds')">
                <div class="auth-mode-icon">⚡</div>
                <div class="auth-mode-title">Default Credentials</div>
                <div class="auth-mode-badge badge-fast">Fast Track</div>
                <div class="auth-mode-desc">Use standard <code>admin</code> / <code>123</code> to enter Order Manager right away.</div>
            </div>

            <div class="auth-mode-card" id="mode-card-custom" onclick="switchAuthMode('custom')">
                <div class="auth-mode-icon">🔒</div>
                <div class="auth-mode-title">Custom Password</div>
                <div class="auth-mode-badge badge-bypass">Standard</div>
                <div class="auth-mode-desc">Bypass PPP grid and set a traditional typed password.</div>
            </div>
        </div>
    </div>

    <?php if ($system_protected): ?>
        <!-- PANEL 0: KEEP CURRENT CREDENTIALS -->
        <div id="auth-panel-keep" class="auth-panel active"
            style="text-align: center; padding: 2rem 1.5rem; background: rgba(15, 23, 42, 0.6); border: 1px solid var(--card-border); border-radius: 14px;">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">🛡️</div>
            <h3 style="font-size: 1.15rem; margin-bottom: 6px; color: white;">Current Credentials Will Be Maintained</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; max-width: 480px; margin: 0 auto 1.2rem;">
                Your active administrator password, passcard row index, and sequence keys will remain untouched. Only business identity, trade presets, and label standards will be updated.
            </p>
            <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(33, 136, 56, 0.15); border: 1px solid rgba(33, 136, 56, 0.3); border-radius: 8px; padding: 8px 16px; color: #4ade80; font-size: 0.85rem; font-weight: 600;">
                ✓ Active Master Password Maintained
            </div>
        </div>
    <?php endif; ?>

    <!-- PANEL 1: PERFECT PAPER PASSWORDS (PPP) -->
    <div id="auth-panel-ppp" class="auth-panel <?= !$system_protected ? 'active' : '' ?>"
        style="<?= $system_protected ? 'display: none;' : '' ?>">
        <!-- Top Toolbar: Length Range & 64-Hex Key -->
        <div class="ppp-control-box">
            <div style="flex: 1; min-width: 140px;">
                <label for="ppp_length_input"
                    style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Password Length</label>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                    <input type="number" id="ppp_length_input" value="<?= $existing_pass_len ?>"
                        min="25" max="80" onchange="onPPPConfigChange()"
                        style="width: 80px; text-align: center; font-weight: 800; font-family: monospace;">
                    <span id="entropy-badge"
                        style="font-size: 0.75rem; color: #38bdf8; font-weight: 600; background: rgba(56, 189, 248, 0.12); padding: 4px 8px; border-radius: 6px;">180-bit Entropy</span>
                </div>
            </div>

            <div style="flex: 2; min-width: 260px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label for="ppp_display_key"
                        style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Sequence Key (128 / 256-Bit Hex)</label>
                    <span id="key-bit-badge"
                        style="font-size: 0.72rem; color: #38bdf8; font-weight: 700; background: rgba(56, 189, 248, 0.12); padding: 2px 6px; border-radius: 4px;">256-bit Key</span>
                </div>
                <div style="display: flex; gap: 6px; margin-top: 4px;">
                    <input type="text" id="ppp_display_key"
                        value="<?= htmlspecialchars($existing_seq_key) ?>"
                        placeholder="Enter 32 or 64-hex key, or generate..."
                        style="font-family: monospace; font-size: 0.8rem; letter-spacing: 0.5px;"
                        oninput="onKeyInputChange()" onchange="applyManualKey()"
                        onkeydown="if(event.key==='Enter'){event.preventDefault();applyManualKey();}">
                    <button type="button" class="btn-tool" onclick="triggerGenKey()"
                        title="Generate Random 64-Hex Key">🎲 Gen Key</button>
                    <button type="button" class="btn-tool" onclick="copySequenceKey()"
                        title="Copy Sequence Key">📋</button>
                    <button type="button" class="btn-tool" id="btn_load_key" onclick="applyManualKey()"
                        title="Load Grid" style="background: var(--accent-gradient); color: white;">🔍 Load</button>
                </div>
            </div>
        </div>

        <!-- Selected Passcode Banner -->
        <div id="selected-passcode-callout" class="ppp-selection-banner"
            style="<?= $existing_row_index > 0 ? '' : 'display:none;' ?>">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Active Secret Passcode:</span>
                    <strong id="active-row-badge"
                        style="color: #38bdf8; font-size: 1rem; margin-left: 6px;">Row <?= str_pad($existing_row_index, 2, '0', STR_PAD_LEFT) ?></strong>
                    <div id="passcode-preview-str"
                        style="font-family: monospace; font-size: 0.85rem; color: #a7f3d0; margin-top: 4px; word-break: break-all;">
                        ••••••••••••••••••••••••••••••
                    </div>
                </div>
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="btn-tool" onclick="togglePasscodeVisibility()"
                        id="btnTogglePasscode">👁️ Reveal</button>
                    <button type="button" class="btn-tool" onclick="copyActivePasscode()">📋 Copy Passcode</button>
                </div>
            </div>
            <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 6px;">
                💡 Keep this card printed or saved offline. When logging into Order Manager, use this row's passcode.
            </div>
        </div>

        <!-- Grid & QR Split Container -->
        <div style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
            <!-- QR Thumbnail -->
            <div class="ppp-qr-box" onclick="viewLargeQR()" title="Click to enlarge Sequence QR Code">
                <img id="ppp_qr_img"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&amp;data=<?= urlencode($existing_seq_key) ?>"
                    alt="PPP Sequence QR Code">
                <span style="font-size: 0.65rem; color: #94a3b8; font-weight: 700; text-transform: uppercase; margin-top: 6px;">Sequence QR</span>
            </div>

            <!-- Passcard Table Preview -->
            <div style="flex: 1; min-width: 280px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1;">Live Passcard Grid Preview</span>
                    <span style="font-size: 0.72rem; color: #38bdf8; font-style: italic;">Click any row to choose it as your secret passcode</span>
                </div>
                <div class="ppp-table-container">
                    <table class="ppp-grid-table">
                        <thead>
                            <tr>
                                <th style="width: 48px;">Row</th>
                                <th>A</th>
                                <th>B</th>
                                <th>C</th>
                                <th>D</th>
                                <th>E</th>
                            </tr>
                        </thead>
                        <tbody id="ppp-grid-tbody">
                            <!-- Dynamically populated -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Action Bar: Print, View, Guide -->
        <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
            <button type="button" class="btn-wizard btn-prev" onclick="printPPPCard()"
                style="flex: 1; min-width: 150px; justify-content: center; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);">
                🖨️ Print Passcard
            </button>
            <button type="button" class="btn-wizard btn-prev" onclick="viewPPPCard()"
                style="flex: 1; min-width: 150px; justify-content: center;">
                📄 View Passcard
            </button>
            <button type="button" class="btn-wizard btn-prev" onclick="togglePPPExplanation()"
                style="padding: 0.8rem 1rem;">
                ❓ What is PPP?
            </button>
        </div>

        <!-- How PPP Works Collapsible Box -->
        <div id="ppp-explanation-box"
            style="display: none; background: rgba(15, 23, 42, 0.8); border: 1px solid var(--card-border); border-radius: 12px; padding: 1.2rem; margin-top: 15px; font-size: 0.82rem; color: #94a3b8; line-height: 1.5;">
            <h4 style="color: white; margin-top: 0; margin-bottom: 6px; font-size: 0.95rem;">🔑 How Perfect Paper Passwords (PPP) Works</h4>
            <p style="margin-bottom: 8px;">Designed by Steve Gibson of Gibson Research Corporation (GRC), PPP is an offline authentication system. Using AES-256 in counter mode, your 64-hexadecimal sequence key generates a pseudo-random 25-row passcard.</p>
            <ul style="padding-left: 20px; margin: 0;">
                <li>Print this passcard and keep it in your wallet, desk drawer, or smartphone.</li>
                <li>When logging into Order Manager, simply enter the passcode from your chosen secret row.</li>
                <li>Your computer never stores the master key in browser storage, defeating keyloggers and database leaks.</li>
            </ul>
        </div>
    </div>

    <!-- PANEL 2: DEFAULT CREDENTIALS FAST TRACK -->
    <div id="auth-panel-default" class="auth-panel" style="display: none;">
        <div class="fast-track-box">
            <div style="font-size: 2.5rem; margin-bottom: 8px;">⚡</div>
            <h3 style="font-size: 1.15rem; margin-bottom: 6px; color: white;">Instant Fast-Track Access Active</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; max-width: 480px; margin: 0 auto 1.2rem;">
                Default warehouse credentials will be committed to <code>users.db</code> so you can log into the Order Manager immediately.
            </p>
            <div class="credentials-badge-box">
                <div class="cred-item">
                    <span class="cred-label">Username</span>
                    <strong class="cred-val">admin</strong>
                </div>
                <div class="cred-divider"></div>
                <div class="cred-item">
                    <span class="cred-label">Password</span>
                    <strong class="cred-val">123</strong>
                </div>
                <div class="cred-divider"></div>
                <div class="cred-item">
                    <span class="cred-label">Access Level</span>
                    <strong class="cred-val" style="color: #38bdf8;">Administrator</strong>
                </div>
            </div>
            <p style="font-size: 0.75rem; color: #10b981; margin-top: 1rem;">
                ✓ Ready to begin! Click "Continue" below to proceed.
            </p>
        </div>
    </div>

    <!-- PANEL 3: CUSTOM PASSWORD BYPASS -->
    <div id="auth-panel-custom" class="auth-panel" style="display: none;">
        <div class="alert-error"
            style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.3); color: #fde68a; margin-bottom: 1.2rem;">
            ⚠️ <strong>PPP Bypass Notice:</strong> Traditional passwords are prone to brute-forcing, dictionary attacks, and keylogging. Consider using Perfect Paper Passwords for high security.
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label for="admin_pass">Custom Admin Password *</label>
                <input type="password" id="admin_pass" name="admin_pass" placeholder="Enter secure password">
                <span class="input-hint">Minimum 4 characters (recommended 12+).</span>
            </div>

            <div class="form-group">
                <label for="admin_pass_confirm">Confirm Password *</label>
                <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" placeholder="Confirm password exactly">
                <span class="input-hint">Repeat the custom password.</span>
            </div>
        </div>
    </div>
</div>
