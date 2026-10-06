<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Systems Setup Wizard | <?= htmlspecialchars($curr_company) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="assets/css/setup.css">
</head>

<body>

    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="wizard-container">

        <?php if ($system_protected && $is_unlocked && !$success): ?>
            <!-- Persistent Live Reconfiguration Warning Banner -->
            <div
                style="background: rgba(245, 158, 11, 0.15); border-bottom: 1px solid rgba(245, 158, 11, 0.35); padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div
                    style="display: flex; align-items: center; gap: 10px; color: #fde68a; font-size: 0.88rem; font-weight: 600;">
                    <span style="font-size: 1.2rem;">⚠️</span>
                    <span><strong>LIVE RECONFIGURATION ACTIVE:</strong> You are modifying active production settings.
                        Changes will update live operations and admin access.</span>
                </div>
                <a href="index.php?lock=1"
                    style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 8px; padding: 6px 14px; font-size: 0.8rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    🔒 Lock &amp; Exit
                </a>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <?php require __DIR__ . '/success.php'; ?>
        <?php else: ?>

            <div class="wizard-header">
                <div class="badge-step">Setup &amp; Brand Wizard</div>
                <h1 class="wizard-title"><?= $reconfigure ? 'System Configuration' : 'Welcome to Warehouse Systems' ?></h1>
                <p class="wizard-subtitle">Tailor the warehouse suite specifically to your business identity, electronics
                    refurbishing standards, and security preferences.</p>
            </div>

            <!-- Progress Nav -->
            <div class="step-nav">
                <div class="step-item active" id="nav-step-1" onclick="jumpToStep(1)">
                    <div class="step-num">1</div>
                    <span class="label-text">Company Profile</span>
                </div>
                <div class="step-item" id="nav-step-2" onclick="jumpToStep(2)">
                    <div class="step-num">2</div>
                    <span class="label-text">Trade Presets</span>
                </div>
                <div class="step-item" id="nav-step-3" onclick="jumpToStep(3)">
                    <div class="step-num">3</div>
                    <span class="label-text">Administrator</span>
                </div>
                <div class="step-item" id="nav-step-4" onclick="jumpToStep(4)">
                    <div class="step-num">4</div>
                    <span class="label-text">Final Review</span>
                </div>
            </div>

            <form method="POST" id="wizardForm">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="complete_setup">

                <div class="wizard-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert-error">
                            ⚠️ <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php require __DIR__ . '/step1_identity.php'; ?>
                    <?php require __DIR__ . '/step2_presets.php'; ?>
                    <?php require __DIR__ . '/step3_auth.php'; ?>
                    <?php require __DIR__ . '/step4_review.php'; ?>
                </div>

                <div class="wizard-footer">
                    <button type="button" class="btn-wizard btn-prev" id="btnPrev" onclick="prevStep()"
                        style="visibility: hidden;">
                        &larr; Back
                    </button>
                    <button type="button" class="btn-wizard btn-next" id="btnNext" onclick="nextStep()">
                        Continue &rarr;
                    </button>
                    <button type="submit" class="btn-wizard btn-submit" id="btnSubmit" style="display: none;">
                        <?= $reconfigure ? '💾 Save &amp; Apply Reconfiguration' : '🚀 Initialize &amp; Launch System' ?>
                    </button>
                </div>
            </form>

        <?php endif; ?>

    </div>

    <!-- Hidden Printable Passcard Source -->
    <div id="ppp-printable-card-source" style="display: none;"></div>

    <script>
        window.SETUP_CONFIG = {
            activeSeqKey: <?= json_encode($existing_seq_key ?? '') ?>,
            selectedRow: <?= (int) ($existing_row_index ?? 0) ?>
        };
    </script>
    <script src="assets/js/setup.js"></script>
</body>

</html>
