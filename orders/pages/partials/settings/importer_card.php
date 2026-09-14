<?php
/**
 * Settings Card 1: Sales Data Importer Partial (Admin Only)
 * Shortcut to the Excel migration tool and formatting reference guide.
 */
if ($_SESSION['role'] !== 'Admin') return;
?>
<!-- SALES DATA IMPORT CARD -->
<div class="settings-card">
    <div class="settings-header">
        <h1>📥 Sales Data Importer</h1>
        <p class="subtitle">Migrate sales records from root .xlsx spreadsheets directly into customers and completed orders.</p>
    </div>
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="font-size: 2rem;">📥</div>
            <div>
                <strong style="display: block; color: var(--text-main); font-size: 0.95rem;">Spreadsheet Data Migration</strong>
                <span style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4; display: block; margin-top: 2px;">Scan, review, and import individual sheets from root sales spreadsheets.</span>
            </div>
        </div>
        <a href="index.php?view=import_sales" class="btn-main" style="background: var(--accent-color); color: white; padding: 12px 20px; font-size: 0.85rem; white-space: nowrap; text-decoration: none; border-radius: 10px; font-weight: 800; display: inline-block;">
            Go to Importer
        </a>
    </div>

    <!-- Collapsible Formatting Guide -->
    <div style="margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
        <button type="button" onclick="const gd = document.getElementById('import-guide-details'); gd.style.display = gd.style.display === 'none' ? 'block' : 'none';" style="background: none; border: none; color: var(--accent-color); font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 0;">
            📖 View Spreadsheet Layout Formatting Guide
        </button>
        <div id="import-guide-details" style="display: none; margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; font-size: 0.85rem; line-height: 1.6; color: #475569;">
            <h3 style="margin-top: 0; color: var(--text-main); font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">📋 Excel Data Structure Rules</h3>
            <p style="margin-bottom: 15px;">To ensure automatic tab imports succeed, each sheet in the spreadsheet must adhere to the following cell rules:</p>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.8rem; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #cbd5e1; font-weight: 800; color: var(--text-main);">
                        <th style="padding: 6px 0; width: 120px;">Cell Reference</th>
                        <th style="padding: 6px 0; width: 150px;">Expected Field</th>
                        <th style="padding: 6px 0;">Rule / Validation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">B3</td>
                        <td style="padding: 8px 0; font-weight: 700;">Customer / Company</td>
                        <td style="padding: 8px 0;">Must not be empty. Matches existing customer by company name, or creates a new profile.</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">B4</td>
                        <td style="padding: 8px 0; font-weight: 700;">Order Date</td>
                        <td style="padding: 8px 0;">Must be a valid Excel numeric date serial value (dates before year 2000 are blocked).</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">B5</td>
                        <td style="padding: 8px 0; font-weight: 700;">Order Number</td>
                        <td style="padding: 8px 0;">Must not be empty. Will be prefixed with <code>ORD-</code> to create the system Order ID.</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">Row 11+</td>
                        <td style="padding: 8px 0; font-weight: 700;">Inventory Items List</td>
                        <td style="padding: 8px 0;">Items must have a Type (Col A), Brand (Col B), QTY (Col G), and Unit Price (Col F). At least 1 item is required.</td>
                    </tr>
                </tbody>
            </table>
            <div style="background: #fffbe5; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; font-size: 0.78rem; color: #92400e;">
                <strong>💡 Validation Note:</strong> Tabs that fail any of the above rules will show a detailed layout error list in the sales importer and will be blocked from import until corrected in the Excel file.
            </div>
        </div>
    </div>
</div>
