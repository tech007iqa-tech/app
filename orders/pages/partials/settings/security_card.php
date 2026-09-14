<?php
/**
 * Settings Card 2: Security & Perfect Paper Passwords (PPP) Partial
 * Manages Steve Gibson GRC PPP passcards, sequence keys, live preview grids, and account password changes.
 */
?>
<!-- UNIFIED PASSWORD UPDATE FORM -->
<form method="POST" id="password-update-form" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 40px;">
    <?= UI::csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="ppp_sequence_key" id="ppp_sequence_key_input" value="<?= htmlspecialchars($seq_key) ?>">
    <input type="hidden" name="ppp_row_index" id="ppp_row_index_input" value="<?= $saved_row_index ?>">

    <!-- Perfect Paper Passwords (PPP) Card (SHOWN FIRST) -->
    <div class="settings-card" id="ppp-card" style="max-width: 600px; width: 100%;">
        <div class="settings-header multi-link-container" style="position: relative;">
            <h1>🔑 Perfect Paper Passwords (PPP)</h1>
            <p class="subtitle">Your offline, ultra-secure one-time passcode system from
                <span class="linked-text-info" style="color: #4f46e5; text-decoration: underline; font-weight: bold; cursor: pointer;">GRC</span>.
            </p>
            <!-- PPP Information Dialog -->
            <div class="info-dialog" style="max-width: 500px; width: 90%;">
                <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
                    <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 How PPP Works</h2>
                    <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                        <strong>Perfect Paper Passwords (PPP)</strong> is an offline, paper-based multi-factor authentication (MFA) system designed by Steve Gibson of Gibson Research Corporation (GRC).
                    </p>
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 8px;">Security Instructions:</h3>
                    <ul style="font-size: 0.85rem; color: #475569; padding-left: 20px; margin-bottom: 16px;">
                        <li style="margin-bottom: 6px;"><strong>Print the Card:</strong> Click the "Print Secure Passcard" button below and print a physical copy. Keep it safely in your wallet.</li>
                        <li style="margin-bottom: 6px;"><strong>Passcode Grid:</strong> The card contains a grid of 50 unique passcodes indexed from Row 01 to 10 and Columns A to E.</li>
                        <li style="margin-bottom: 6px;"><strong>Authentication:</strong> When signing in, the system will ask you to enter a passcode from a specific cell (e.g. <code>03-B</code>). Find that cell on your printed card and type the characters.</li>
                        <li style="margin-bottom: 6px;"><strong>One-Time Use:</strong> Once you use a passcode, you are in. (<i>If you log out and do not know your password, please tell the Admin ASAP</i>)</li>
                        <li style="margin-bottom: 6px;"><strong>No Secrets Stored Online:</strong> The server only stores a master Sequence Key, never the passcodes themselves.</li>
                    </ul>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 0.8rem; color: #64748b; font-style: italic;">
                        Tip: If your physical card is lost or compromised, change your password and automatically generate a brand new card.<br><br>
                        <strong>Important Note:</strong> To reprint or regenerate your original passcard, you MUST keep a backup of both your 64-character <strong style="color: black">Sequence Key</strong> and the corresponding <strong style="color: black">Password Length</strong> (e.g., 30).
                    </div>
                </div>
            </div>
        </div>

        <!-- Length Range Controls (Only shown in PPP card if password change is forced) -->
        <?php if ($is_forced): ?>
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; align-items: center;">
                <div style="flex: 1; min-width: 150px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Password Length Range</label>
                    <input type="number" id="ppp_length_input" name="ppp_length" value="<?= $saved_pass_len ?>" min="25" max="80" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: bold; text-align: center;" onchange="onLengthChange()">
                    <span style="font-size: 0.7rem; color: #64748b; margin-top: 4px; display: block; line-height: 1.3;">
                        Recommended: 25-50. Higher is more secure.
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px;">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                    <input type="text" value="<?= htmlspecialchars($seq_key) ?>" placeholder="Generate a key to start..." readonly style="font-family: monospace; font-size: 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; flex: 1; text-align: center;" id="ppp_display_key">
                    <button type="button" onclick="copySequenceKey()" style="background: #e2e8f0; color: #475569; border: none; padding: 0 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; height: 34px;">📋</button>
                </div>
                <?php if ($is_forced): ?>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="btn-main" onclick="triggerGenKey()" style="background:#64748b; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🎲 Gen Key</button>

                        <div class="multi-link-container" style="position: relative;">
                            <button type="button" class="btn-main linked-text-info" style="background:#4f46e5; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🔍 Verify & Load Key</button>

                            <!-- Input Sequence Key Dialog -->
                            <div class="info-dialog" id="dialog_seq_key" style="max-width: 500px; width: 90%;">
                                <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                                <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
                                    <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 Load Sequence Key</h2>
                                    <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                                        Enter an existing 32 or 64-character hexadecimal Sequence Key (128-bit or 256-bit) to generate and preview its passcard grid.<br><br>
                                        <strong>Note:</strong> You must also match the exact <em>Password Length Range</em> that was configured when generating the key to reprint/regenerate the original passcard cells.
                                    </p>
                                    <div class="form-group" style="margin-bottom: 20px;">
                                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                                        <input type="text" id="manual_seq_key_input_forced" placeholder="e.g. 4FD20961... or 1DBED7E3..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 0.85rem; box-sizing: border-box;" oninput="onManualKeyInput(this.value)">
                                        <span id="manual_key_error_forced" style="font-size: 0.75rem; color: #ef4444; margin-top: 4px; display: none;">Key must be between 32 and 64 hexadecimal characters.</span>
                                    </div>
                                    <button type="button" class="btn-main" onclick="applyManualKey(true)" style="width: 100%; padding: 12px; border-radius: 8px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                                        Load Grid
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div id="qr-container-wrapper" class="multi-link-container" style="display: <?= empty($seq_key) ? 'none' : 'flex' ?>; flex-direction: column; align-items: center; justify-content: center; background: white; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <span class="linked-text-img" title="Click to enlarge QR Code" id="qr-clickable-zone">
                    <img id="ppp_qr_img" src="<?= !empty($seq_key) ? 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=' . urlencode($seq_key) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>" alt="PPP QR Code" style="width: 110px; height: 110px; border-radius: 8px; display: block;padding:10%;">
                </span>
                <span style="font-size: 0.65rem; color: #64748b; font-weight: 800; margin-top: 6px; text-transform: uppercase;">Sequence QR Code</span>

                <!-- Image Dialog Modal -->
                <div class="image-dialog" id="qr-modal-dialog">
                    <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                    <figure>
                        <img id="ppp_qr_large_img" src="<?= !empty($seq_key) ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($seq_key) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>" alt="Enlarged PPP QR Code" style="padding: 10%;">
                        <figcaption id="ppp_qr_caption"><a href="#" onclick="printQRCode(); return false;"><span style="font-family: monospace; font-size: 0.8rem; word-break: break-all; margin-top: 5px; display: block;">Sequence Key:</a><br><span id="ppp_qr_caption_key"><?= htmlspecialchars($seq_key) ?></span></span></figcaption>
                    </figure>
                </div>
            </div>
        </div>

        <!-- Table and Grid Preview -->
        <div id="ppp-grid-section" style="border-top: 1px dashed var(--border-color); padding-top: 24px; margin-top: 20px; display: <?= empty($seq_key) ? 'none' : 'block' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin: 0;">Live Passcard Grid Preview</h3>

                <label id="ppp_show_active_container" style="display: none; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-main); cursor: pointer; user-select: none; margin: 0;">
                    <input type="checkbox" id="ppp_show_active_checkbox" onchange="toggleShowActive()" style="width: 16px; height: 16px;">
                    Show active password in grid preview
                </label>
            </div>

            <div style="max-height: 250px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 12px; background: white; margin-bottom: 20px; position: relative;">
                <table style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8rem; text-align: center; font-family: monospace; table-layout: fixed;">
                    <thead>
                        <tr>
                            <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; width: 60px; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">Row</th>
                            <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">A</th>
                            <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">B</th>
                            <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">C</th>
                            <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">D</th>
                            <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">E</th>
                        </tr>
                    </thead>
                    <tbody id="ppp-grid-tbody">
                        <?php
                        $cell_len = (int)ceil($saved_pass_len / 5.0);
                        $actual_codes = !empty($seq_key) ? generate_ppp_passcodes($seq_key, $cell_len) : [];

                        for ($r = 0; $r < 25; $r++) {
                            $row_num = sprintf('%02d', $r + 1);
                            $is_saved_row = ($saved_row_index === ($r + 1));
                            $bg = $is_saved_row ? '#e0f2fe' : (($r % 2 === 0) ? '#f8fafc' : '#ffffff');
                            $padding_top = ($r === 0) ? '14px' : '10px';

                            echo "<tr data-row-num='" . ($r + 1) . "' style='background: {$bg}; cursor: pointer;' onclick='onRowClick(this, " . ($r + 1) . ")'>";
                            echo "<td style='padding: {$padding_top} 4px 10px 4px; font-weight: bold; color: #64748b; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 60px;'>{$row_num}</td>";

                            for ($c = 0; $c < 5; $c++) {
                                $code_val = !empty($actual_codes) ? $actual_codes[$r * 5 + $c] : '';
                                $border_right = ($c < 4) ? 'border-right: 1px solid #e2e8f0;' : '';
                                echo "<td class='ppp-cell' style='padding: {$padding_top} 4px 10px 4px; font-weight: bold; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; {$border_right} word-break: break-all;'>" . htmlspecialchars($code_val) . "</td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="button" onclick="printPPPCard()" class="btn-main" style="flex: 1; padding: 14px; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px;">
                    🖨️ Print Passcard
                </button>
                <button type="button" onclick="viewPPPCard()" class="btn-main" style="flex: 1; padding: 14px; border-radius: 12px; background: #64748b; color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px;">
                    📄 View / Save Passcard
                </button>
            </div>
        </div>
    </div>

    <!-- Account Security Card (SHOWN SECOND) -->
    <div class="settings-card">
        <div class="settings-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h1>Account Security</h1>
                <p class="subtitle">Update your password to keep your account secure.</p>
            </div>
        </div>

        <?php if (!$is_forced): ?>
            <!-- Password Length Range & Gen Key relocated here when logged in securely -->
            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; align-items: center;">
                <div style="flex: 1; min-width: 150px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Password Length Range</label>
                    <input type="number" id="ppp_length_input" name="ppp_length" value="<?= $saved_pass_len ?>" min="25" max="80" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: bold; text-align: center;" onchange="onLengthChange()">
                    <span style="font-size: 0.7rem; color: #64748b; margin-top: 4px; display: block; line-height: 1.3;">
                        Recommended: 25-50. Higher is more secure.
                    </span>
                </div>
                <div style="flex: 1; min-width: 150px; display: flex; flex-direction: column; align-items: flex-start; justify-content: center;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Perfect Paper Passcode</label>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="btn-main" onclick="triggerGenKey()" style="background:#64748b; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">
                            🎲 Gen Key
                        </button>

                        <div class="multi-link-container" style="position: relative;">
                            <button type="button" class="btn-main linked-text-info" style="background:#4f46e5; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🔍 Verify & Load Key</button>

                            <!-- Input Sequence Key Dialog -->
                            <div class="info-dialog" id="dialog_seq_key_secure" style="max-width: 500px; width: 90%;">
                                <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                                <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
                                    <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 Load Sequence Key</h2>
                                    <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                                        Enter an existing 32 or 64-character hexadecimal Sequence Key (128-bit or 256-bit) to generate and preview its passcard grid.<br><br>
                                        <strong>Note:</strong> You must also match the exact <em>Password Length Range</em> that was configured when generating the key to reprint/regenerate the original passcard cells.
                                    </p>
                                    <div class="form-group" style="margin-bottom: 20px;">
                                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                                        <input type="text" id="manual_seq_key_input_secure" placeholder="e.g. 4FD20961... or 1DBED7E3..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 0.85rem; box-sizing: border-box;" oninput="onManualKeyInput(this.value)">
                                        <span id="manual_key_error_secure" style="font-size: 0.75rem; color: #ef4444; margin-top: 4px; display: none;">Key must be between 32 and 64 hexadecimal characters.</span>
                                    </div>
                                    <button type="button" class="btn-main" onclick="applyManualKey(false)" style="width: 100%; padding: 12px; border-radius: 8px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                                        Load Grid
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="old_password">Current Password</label>
                <input type="password" id="old_password" name="old_password" placeholder="••••••••" required>
            </div>
        <?php endif; ?>

        <div style="border-top: 1px dashed var(--border-color); padding-top: 20px; margin-top: 20px;">
            <!-- Bypass PPP Checkbox and Warnings -->
            <div style="margin-bottom: 20px; background: #fffbeb; border: 1px solid #fde68a; padding: 15px; border-radius: 12px; box-sizing: border-box;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; user-select: none; text-transform: none; color: #b45309; font-weight: bold; font-size: 0.9rem; line-height: 1.4; margin-bottom: 0;">
                    <input type="checkbox" name="bypass_ppp" id="bypass_ppp" value="1" onchange="toggleBypassPPP(this.checked)" style="width: 18px; height: 18px; margin: 0; margin-top: 2px;" <?= (isset($_POST['bypass_ppp']) && $_POST['bypass_ppp'] === '1') ? 'checked' : '' ?>>
                    <span>Bypass Perfect Paper Passwords (PPP) grid and set a custom password</span>
                </label>
                <div id="ppp-bypass-warning" style="display: none; margin-top: 10px; font-size: 0.8rem; color: #b45309; line-height: 1.4; border-top: 1px dashed #fcd34d; padding-top: 8px;">
                    ⚠️ <strong>Security Warning:</strong> Bypassing the PPP system allows you to use a custom password. Custom passwords are significantly more vulnerable to keylogging, guessing, and database leaks than GRC's pseudo-random high-entropy passcodes. Make sure to choose a strong password containing letters, numbers, and symbols.
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" placeholder="Min 24 chars, complex (A-Z, a-z, 0-9, symbol)" <?= $is_forced ? 'readonly' : '' ?> required>
                <!-- Password Strength Meter -->
                <div id="strength-meter-container" style="margin-top: 8px; display: none; flex-direction: column; gap: 5px;">
                    <div style="background: #e2e8f0; height: 6px; width: 100%; border-radius: 3px; overflow: hidden;">
                        <div id="strength-meter-bar" style="height: 100%; width: 0%; transition: width 0.3s ease, background-color 0.3s ease;"></div>
                    </div>
                    <span id="strength-meter-text" style="font-size: 0.75rem; font-weight: bold;"></span>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 30px;">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>
        </div>
        <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
            💾 Update Password
        </button>
    </div>
</form>

<!-- PRINTABLE PASSCARD SOURCE -->
<div id="ppp-printable-card-source" style="display: none;"></div>
