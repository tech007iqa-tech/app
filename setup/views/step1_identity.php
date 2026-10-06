<!-- STEP 1: COMPANY PROFILE -->
<div class="step-content active" id="step-1">
    <h2 style="font-size: 1.25rem; margin-bottom: 6px;">🏢 Company Profile &amp; Identity</h2>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">Set your business
        brand details. These will appear in the portal, document headers, manifests, and receipts.</p>

    <div class="form-grid">
        <div class="form-group">
            <label for="company_name">Company Name *</label>
            <input type="text" id="company_name" name="company_name"
                value="<?= htmlspecialchars($curr_company) ?>" placeholder="e.g. IQA Metal" required>
            <span class="input-hint">Your registered trade or store brand name.</span>
        </div>

        <div class="form-group">
            <label for="system_name">System Portal Title *</label>
            <input type="text" id="system_name" name="system_name"
                value="<?= htmlspecialchars($curr_system) ?>"
                placeholder="e.g. IQA Metal Warehouse Systems" required>
            <span class="input-hint">Displays in the browser tab and portal header.</span>
        </div>

        <div class="form-group">
            <label for="company_url">Official Website / Domain</label>
            <input type="url" id="company_url" name="company_url"
                value="<?= htmlspecialchars($curr_url) ?>" placeholder="https://iqametal.com">
            <span class="input-hint">Linked in footer notes and manifest signatures.</span>
        </div>

        <div class="form-group">
            <label for="support_email">Contact / Operations Email</label>
            <input type="email" id="support_email" name="support_email"
                value="<?= htmlspecialchars($curr_email) ?>" placeholder="sales@iqametal.com">
            <span class="input-hint">Receives system alerts and customer inquiries.</span>
        </div>

        <div class="form-group">
            <label for="currency_symbol">Currency Symbol</label>
            <select id="currency_symbol" name="currency_symbol">
                <option value="$" <?= $curr_currency === '$' ? 'selected' : '' ?>>$ (USD - US Dollar)</option>
                <option value="C$" <?= $curr_currency === 'C$' ? 'selected' : '' ?>>C$ (CAD - Canadian Dollar)</option>
                <option value="MX$" <?= $curr_currency === 'MX$' ? 'selected' : '' ?>>MX$ (MXN - Mexican Peso)</option>
                <option value="€" <?= $curr_currency === '€' ? 'selected' : '' ?>>€ (EUR - Euro)</option>
                <option value="£" <?= $curr_currency === '£' ? 'selected' : '' ?>>£ (GBP - British Pound)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="tagline">Operations Tagline</label>
            <input type="text" id="tagline" name="tagline"
                value="<?= htmlspecialchars($curr_tagline) ?>"
                placeholder="e.g. Intelligent inventory management & rapid label logistics.">
            <span class="input-hint">Short mission description on the landing page.</span>
        </div>
    </div>
</div>
