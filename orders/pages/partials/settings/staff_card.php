<?php
/**
 * Settings Card 4: Staff Management Partial (Admin Only)
 * Allows administrators to add new staff members, update user access roles, and revoke accounts.
 */
if ($_SESSION['username'] !== 'admin') return;
?>
<!-- 3. USER MANAGEMENT CARD (ADMIN ONLY) -->
<div class="settings-card">
    <div class="settings-header">
        <h1>Staff Management</h1>
        <p class="subtitle">Assign additional accounts to help manage inventory batches.</p>
    </div>

    <form method="POST" style="background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0;">
        <?= UI::csrf_field() ?>
        <input type="hidden" name="action" value="add_user">
        <div style="display: flex; gap: 15px; margin-bottom: 12px;">
            <div class="form-group" style="flex: 2;">
                <label for="new_username">New Username</label>
                <input type="text" id="new_username" name="new_username" placeholder="e.g. omar_sales" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="new_role">Access Level</label>
                <select name="new_role" id="new_role" style="width:100%; height:44px; border-radius:10px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                    <option value="Operator">Operator</option>
                    <option value="Front Desk">Front Desk</option>
                    <option value="Technician">Technician</option>
                    <option value="Admin">Administrator</option>
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-bottom: 18px;">
            <label for="staff_password">Assign Password</label>
            <input type="password" id="staff_password" name="new_password" placeholder="Min 24 chars, complex (A-Z, a-z, 0-9, symbol)" required>
        </div>
        <button type="submit" class="btn-main" style="width: 100%; height: 44px; border-radius: 10px; background: var(--accent-color); color: white; border: none; font-weight: 800; cursor: pointer;">
            ⊕ Add New Staff Member
        </button>
    </form>

    <ul class="user-list">
        <li style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 10px;">Active Accounts</li>
        <?php
            $users = $conn_u->query("SELECT username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach($users as $u) {
                $is_admin = ($u['username'] === 'admin');
                $user_role = $u['role'] ?? 'Operator';

                echo "<li class='user-item'>
                        <div style='display:flex; flex-direction:column;'>
                            <span class='user-name'>" . htmlspecialchars($u['username']) . ($is_admin ? " <small style='color:var(--accent-color)'>(Root)</small>" : "") . "</span>
                            <span style='font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase;'>" . htmlspecialchars($user_role) . "</span>
                        </div>";

                if (!$is_admin) {
                    echo "<div style='display:flex; gap:8px;'>";

                    // Role Toggle Buttons
                    if ($user_role === 'Operator') {
                        echo "<form method='POST' style='display:inline;'>
                                " . UI::csrf_field() . "
                                <input type='hidden' name='action' value='change_role'>
                                <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                <input type='hidden' name='target_role' value='Front Desk'>
                                <button type='submit' class='btn-delete-small' style='background:#dcfce7; color:#166534;'>Promote</button>
                              </form>";
                    } elseif ($user_role === 'Front Desk') {
                        echo "<form method='POST' style='display:inline;'>
                                " . UI::csrf_field() . "
                                <input type='hidden' name='action' value='change_role'>
                                <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                <input type='hidden' name='target_role' value='Admin'>
                                <button type='submit' class='btn-delete-small' style='background:#dcfce7; color:#166534;'>Promote</button>
                              </form>";
                        echo "<form method='POST' style='display:inline;'>
                                " . UI::csrf_field() . "
                                <input type='hidden' name='action' value='change_role'>
                                <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                <input type='hidden' name='target_role' value='Operator'>
                                <button type='submit' class='btn-delete-small' style='background:#e2e8f0; color:#475569;'>Demote</button>
                              </form>";
                    } elseif ($user_role === 'Technician') {
                        echo "<form method='POST' style='display:inline;'>
                                " . UI::csrf_field() . "
                                <input type='hidden' name='action' value='change_role'>
                                <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                <input type='hidden' name='target_role' value='Operator'>
                                <button type='submit' class='btn-delete-small' style='background:#e0e7ff; color:#4338ca;'>→ Operator</button>
                              </form>";
                    } elseif ($user_role === 'Admin') {
                        echo "<form method='POST' style='display:inline;'>
                                " . UI::csrf_field() . "
                                <input type='hidden' name='action' value='change_role'>
                                <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                <input type='hidden' name='target_role' value='Front Desk'>
                                <button type='submit' class='btn-delete-small' style='background:#e2e8f0; color:#475569;'>Demote</button>
                              </form>";
                    }

                    // Revoke Access
                    echo "<form method='POST' style='display:inline;' onsubmit=\"return confirm('Remove access for this user?');\">
                            " . UI::csrf_field() . "
                            <input type='hidden' name='action' value='delete_user'>
                            <input type='hidden' name='del_username' value='" . htmlspecialchars($u['username']) . "'>
                            <button type='submit' class='btn-delete-small'>Revoke</button>
                          </form>";

                    echo "</div>";
                }
                echo "</li>";
            }
        ?>
    </ul>
</div>
