<?php
/**
 * Reports Module - Strategic Insights & Performance Tracking
 * Resilient aggregation queries, zero-division guards, and multi-DB metrics.
 */

// 1. Fetch Stats Resiliently
$totalLeads = 0;
$newLeads = 0;
$convertedLeads = 0;
$crmSynced = 0;

try {
    if ($marketingDb) {
        $totalLeads = (int)($marketingDb->query("SELECT COUNT(*) FROM leads")->fetchColumn() ?: 0);
        $newLeads = (int)($marketingDb->query("SELECT COUNT(*) FROM leads WHERE status = 'New' OR status = 'Lead'")->fetchColumn() ?: 0);
        $convertedLeads = (int)($marketingDb->query("SELECT COUNT(*) FROM leads WHERE status = 'Customer' OR status = 'ACTIVE CUSTOMER'")->fetchColumn() ?: 0);
        $crmSynced = (int)($marketingDb->query("SELECT COUNT(*) FROM leads WHERE customer_id IS NOT NULL")->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    error_log("Failed to fetch lead reporting metrics: " . $e->getMessage());
}

// 2. Fetch Warehouse Coverage
$totalWarehouseModels = 0;
$marketedModels = 0;
try {
    if ($labelsDb) {
        $totalWarehouseModels = (int)($labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn() ?: 0);
    }
    if ($marketingDb) {
        $marketedModels = (int)($marketingDb->query("SELECT COUNT(DISTINCT model_name) FROM model_templates")->fetchColumn() ?: 0);
    }
} catch (Throwable $e) {
    error_log("Failed to fetch inventory coverage metrics: " . $e->getMessage());
}

// 3. Calculate percentages with zero-division protection
$funnelWidth = $totalLeads > 0 ? (int)round(($convertedLeads / $totalLeads) * 100) : 0;
$coveragePct = $totalWarehouseModels > 0 ? (int)round(($marketedModels / $totalWarehouseModels) * 100) : 0;
?>

<header class="page-header">
    <h1>Marketing Insights</h1>
    <p>Automated reporting on inventory coverage, lead conversion, and CRM synchronization.</p>
</header>

<div class="dashboard-grid">
    <!-- LEAD FUNNEL -->
    <section class="card">
        <h2>📈 Conversion Funnel</h2>
        <div style="margin-top: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Leads-to-Customer Rate</span>
                    <strong><?= $funnelWidth; ?>%</strong>
                </div>
                <div style="height: 12px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
                    <div style="width: <?= min(100, $funnelWidth); ?>%; height: 100%; background: var(--accent-primary);"></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="stat-box">
                    <div class="label">Total Leads</div>
                    <div class="stat" style="font-size: 1.5rem; font-weight: 700;"><?= $totalLeads; ?></div>
                </div>
                <div class="stat-box">
                    <div class="label">Converted</div>
                    <div class="stat" style="font-size: 1.5rem; font-weight: 700; color: var(--accent-primary);"><?= $convertedLeads; ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- INVENTORY COVERAGE -->
    <section class="card">
        <h2>📦 Warehouse Coverage</h2>
        <div style="margin-top: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Models in Marketing Library</span>
                    <strong><?= $coveragePct; ?>%</strong>
                </div>
                <div style="height: 12px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
                    <div style="width: <?= min(100, $coveragePct); ?>%; height: 100%; background: var(--accent-tertiary);"></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="stat-box">
                    <div class="label">Whse Models</div>
                    <div class="stat" style="font-size: 1.5rem; font-weight: 700;"><?= $totalWarehouseModels; ?></div>
                </div>
                <div class="stat-box">
                    <div class="label">Branded</div>
                    <div class="stat" style="font-size: 1.5rem; font-weight: 700; color: var(--accent-primary);"><?= $marketedModels; ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- CRM SYNC HEALTH -->
    <section class="card">
        <h2>🔄 CRM Sync Health</h2>
        <div style="text-align: center; padding: 1rem 0;">
            <div style="font-size: 3rem; font-weight: 800; color: var(--accent-primary);"><?= $crmSynced; ?> / <?= $totalLeads; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Leads currently synced with Master CRM</p>

            <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px; font-size: 0.85rem; color: var(--text-dim); border: 1px solid var(--border-color);">
                <?php if ($crmSynced >= $totalLeads && $totalLeads > 0): ?>
                    ✅ Your marketing database is 100% in sync with the Master CRM.
                <?php elseif ($totalLeads === 0): ?>
                    ℹ️ No leads found yet. Add contacts or click "Sync CRM" on the Leads page.
                <?php else: ?>
                    ⚠️ <?= ($totalLeads - $crmSynced); ?> leads are local-only. Use the "Sync CRM" button on the Leads page to push them.
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<style>
.stat-box {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}
.stat-box .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--text-dim);
    margin-bottom: 5px;
}
</style>
