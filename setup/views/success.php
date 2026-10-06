<!-- Success Screen -->
<div class="wizard-header">
    <div class="badge-step"
        style="background: rgba(33, 136, 56, 0.2); color: #4ade80; border: 1px solid rgba(33, 136, 56, 0.4);">✓
        <?= $reconfigure ? 'Reconfiguration Applied' : '🚀 Initialization Complete' ?></div>
    <h1 class="wizard-title"><?= htmlspecialchars($company_name) ?>
        <?= $reconfigure ? 'Updated!' : 'is Ready!' ?></h1>
    <p class="wizard-subtitle">Your warehouse management suite configuration has been safely updated in
        production. All databases are isolated outside HTTP scope.</p>
</div>

<div class="wizard-body success-box">
    <div class="success-icon">✨</div>
    <h2 style="font-size: 1.4rem; margin-bottom: 0.5rem;">Welcome to your new operations hub</h2>
    <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto;">Company profile, electronics
        refurbishing presets, and administrator credentials have been stored successfully.</p>

    <div class="launch-grid">
        <a href="../tech/index.php" class="launch-card">
            <div class="launch-icon">🔧</div>
            <h3>Technician Center</h3>
            <p>Diagnostics, testing logs, grading, and parts inventory.</p>
        </a>
        <a href="../orders/index.php" class="launch-card">
            <div class="launch-icon">📊</div>
            <h3>Order Manager</h3>
            <p>Batch fulfillment, customer registry, and manifest logistics.</p>
        </a>
        <a href="../marketing/index.php" class="launch-card">
            <div class="launch-icon">📣</div>
            <h3>Marketing Hub</h3>
            <p>Lead generation, photo bucket, and sales copy automation.</p>
        </a>
    </div>

    <div style="margin-top: 2.5rem;">
        <a href="../index.php" class="btn-wizard btn-next" style="padding: 1rem 2.5rem; font-size: 1rem;">
            Enter Main Portal &rarr;
        </a>
    </div>
</div>
