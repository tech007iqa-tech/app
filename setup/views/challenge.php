<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification Required | <?= htmlspecialchars($curr_company) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/components.css">
    <style>
        :root {
            --primary-color: #0056b3;
            --primary-dark: #082d45;
            --secondary-color: #218838;
            --secondary-dark: #155724;
            --bannerAndFooter-bg: #daedfb;
            --bg-base: #041521;
            --card-bg: rgba(8, 45, 69, 0.85);
            --card-border: rgba(218, 237, 251, 0.14);
            --accent-primary: #38bdf8;
            --accent-gradient: linear-gradient(135deg, #0056b3 0%, #38bdf8 50%, #218838 100%);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: rgba(4, 21, 33, 0.75);
            --input-border: rgba(218, 237, 251, 0.16);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
            background: radial-gradient(circle at 50% 0%, rgba(0, 86, 179, 0.25) 0%, transparent 60%),
                linear-gradient(135deg, #041521 0%, #082d45 100%);
        }

        .glow-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.35;
            pointer-events: none;
        }

        .blob-1 {
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: #dc2626;
            opacity: 0.2;
        }

        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: #0056b3;
        }

        .wizard-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 580px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(239, 68, 68, 0.35);
            border-radius: 24px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8), 0 0 40px rgba(239, 68, 68, 0.15);
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.18);
            border: 1px solid rgba(239, 68, 68, 0.45);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-wizard {
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 700;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
        }

        .btn-submit {
            background: linear-gradient(135deg, #dc2626 0%, #ea580c 50%, #f59e0b 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.35);
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
        }

        .btn-prev {
            background: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
            border: 1px solid var(--card-border);
        }

        .btn-prev:hover {
            background: rgba(255, 255, 255, 0.14);
            color: white;
        }

        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 0.85rem 1rem;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            color: white;
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s;
        }

        input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }
    </style>
</head>

<body>
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="wizard-container">
        <!-- Prominent Red/Amber System Security Banner -->
        <div
            style="background: linear-gradient(90deg, rgba(220, 38, 38, 0.25) 0%, rgba(245, 158, 11, 0.25) 100%); border-bottom: 1px solid rgba(239, 68, 68, 0.4); padding: 12px 24px; text-align: center; color: #fca5a5; font-size: 0.85rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase;">
            ⚠️ Active Production System &bull; Password Protected
        </div>

        <div
            style="padding: 2.5rem 2.5rem 1.25rem; text-align: center; border-bottom: 1px solid var(--card-border); background: rgba(255, 255, 255, 0.02);">
            <div
                style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); border: 2px solid rgba(239, 68, 68, 0.4); font-size: 2rem; margin-bottom: 12px; box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);">
                🛡️
            </div>
            <h1
                style="font-size: 1.85rem; font-weight: 800; background: linear-gradient(135deg, #fca5a5 0%, #fbbf24 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px;">
                Administrator Verification
            </h1>
            <p
                style="font-size: 0.92rem; color: var(--text-muted); max-width: 480px; margin: 0 auto; line-height: 1.5;">
                Reconfiguration access is restricted to verified administrators to safeguard active database records and
                operational settings.
            </p>
        </div>

        <div style="padding: 2rem 2.5rem 2.5rem;">
            <?php if (!empty($unlock_error)): ?>
                <div class="alert-error">
                    <span style="font-size: 1.3rem;">🚫</span>
                    <span><?= htmlspecialchars($unlock_error) ?></span>
                </div>
            <?php endif; ?>

            <div
                style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 1.2rem; margin-bottom: 1.75rem; font-size: 0.84rem; color: #cbd5e1; line-height: 1.5;">
                <div
                    style="display: flex; align-items: center; gap: 8px; color: #fbbf24; font-weight: 700; margin-bottom: 6px;">
                    <span>⚠️</span>
                    <span>System Warning: Active Warehouse Environment</span>
                </div>
                This installation has a password in place and is live. Modifying system identity, trade presets, or
                authentication parameters directly impacts active orders, technician diagnostics, and staff logins.
            </div>

            <form method="POST" action="index.php?reconfigure=1">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="unlock_reconfigure">

                <div style="margin-bottom: 1.5rem;">
                    <label for="admin_password"
                        style="display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; font-weight: 700; color: #e2e8f0; margin-bottom: 6px;">
                        <span>Current Administrator Password *</span>
                        <span style="font-size: 0.72rem; color: var(--accent-primary); font-weight: 500;">(Password or
                            PPP row passcode)</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="admin_password" name="admin_password" required autofocus
                            placeholder="Enter administrator password..." style="padding-right: 44px;">
                        <button type="button" onclick="togglePassVisibility('admin_password')"
                            style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; padding: 4px;"
                            title="Toggle visibility">
                            👁️
                        </button>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="submit" class="btn-wizard btn-submit"
                        style="width: 100%; justify-content: center; padding: 0.95rem; font-size: 0.95rem;">
                        🔓 Verify Password &amp; Unlock Wizard
                    </button>
                    <a href="../index.php" class="btn-wizard btn-prev"
                        style="width: 100%; justify-content: center; text-decoration: none; padding: 0.8rem; font-size: 0.85rem; text-align: center;">
                        &larr; Cancel &amp; Return to Warehouse Portal
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassVisibility(id) {
            const input = document.getElementById(id);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>

</html>
