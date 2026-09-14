<?php
/**
 * Batch Import Center Modal Partial
 * Tabbed modal supporting Clipboard smart parsing, Warehouse stock migration, and Gemini Vision AI Work Order OCR.
 */
?>
<!-- Import Modal -->
<div id="import-modal" class="modal-overlay"
    style="display: none; overflow-y: auto; align-items: flex-start; padding: 0;" onclick="closeImportModal()">
    <div class="modal-card" onclick="event.stopPropagation()" style="max-width: none; width: 100vw; min-height: 100vh; margin: 0; border-radius: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-weight: 900; margin: 0; font-size: 1.5rem; text-align: left;">📦 Batch Import Center</h2>
            <button class="btn-repeat" onclick="closeImportModal()"
                style="border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 900;">✖</button>
        </div>

        <!-- Tabs Header -->
        <div class="import-tabs"
            style="display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; gap: 10px;">
            <button type="button" onclick="switchImportTab('clipboard')" id="tab-btn-clipboard" class="import-tab-btn"
                style="padding: 10px 20px; font-weight: 800; border: none; background: none; border-bottom: 3px solid var(--accent-color); cursor: pointer; color: var(--text-main); font-size: 0.95rem; outline: none;">📋
                Clipboard</button>
            <button type="button" onclick="switchImportTab('warehouse')" id="tab-btn-warehouse" class="import-tab-btn"
                style="padding: 10px 20px; font-weight: 700; border: none; background: none; border-bottom: 3px solid transparent; cursor: pointer; color: #64748b; font-size: 0.95rem; outline: none;">📦
                Warehouse Stock</button>
            <button type="button" onclick="switchImportTab('workorder')" id="tab-btn-workorder" class="import-tab-btn"
                style="padding: 10px 20px; font-weight: 700; border: none; background: none; border-bottom: 3px solid transparent; cursor: pointer; color: #64748b; font-size: 0.95rem; outline: none;">📝
                Work Order AI</button>
        </div>

        <div style="padding: 0;">
            <!-- Tab 1: Clipboard -->
            <div id="import-tab-clipboard-content">
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px; text-align: left;">
                    Paste your data below. Delimiter format is auto-detected. Excel/Sheets and CSV lists are fully
                    supported.
                </p>

                <textarea id="import-paste-area" placeholder="Paste rows here..."
                    style="width: 100%; height: 250px; border-radius: 12px; border: 2px solid #e2e8f0; padding: 15px; font-family: monospace; font-size: 0.85rem; resize: none; margin-bottom: 20px; outline: none; transition: border-color 0.2s; background: #f8fafc;"
                    onfocus="this.style.borderColor='var(--accent-color)'"
                    onblur="this.style.borderColor='#e2e8f0'"></textarea>

                <div id="import-preview" style="margin-bottom: 20px; display: none;">
                    <div id="import-mapping-info"></div>
                    <h3
                        style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; text-align: left;">
                        Preview: <span id="import-row-count">0</span> rows detected</h3>
                    <div
                        style="max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.7rem; background: #f8fafc;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;"
                            id="import-preview-table">
                            <!-- Populated by JS -->
                        </table>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="processImport()" id="btn-submit-import" class="btn-save"
                        style="flex: 2; height: 50px; font-weight: 800; cursor: pointer; border: none; border-radius: 12px;">🚀
                        Start Bulk Import</button>
                    <button type="button" onclick="closeImportModal()" class="btn-cancel"
                        style="flex: 1; height: 50px; font-weight: 700; cursor: pointer; border: none; border-radius: 12px;">Cancel</button>
                </div>
            </div>

            <!-- Tab 2: Warehouse Stock -->
            <div id="import-tab-warehouse-content" style="display: none;">
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px; text-align: left;">
                    Search and select items from the warehouse system to copy into this order. Selected items will be
                    flagged in the warehouse system for manual deletion.
                </p>
                <div style="margin-bottom: 20px;">
                    <input type="text" id="wh-import-q" onkeyup="searchWarehouseImport()"
                        placeholder="Search by brand, model, location, or specs..."
                        style="width: 100%; height: 44px; padding: 0 15px; border-radius: 10px; border: 1px solid var(--border-color); outline: none;">
                </div>

                <div
                    style="max-height: 250px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; background: #f8fafc;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8rem;"
                        id="wh-import-table">
                        <thead>
                            <tr style="background: #f1f5f9; position: sticky; top: 0; z-index: 10;">
                                <th style="padding: 10px; width: 40px; text-align: center;"><input type="checkbox"
                                        id="wh-import-select-all" onchange="toggleAllWarehouseImport(this)"></th>
                                <th style="padding: 10px; width: 120px;">Location</th>
                                <th style="padding: 10px; width: 220px;">Make/Model</th>
                                <th style="padding: 10px; width: 60px; text-align: center;">QTY</th>
                                <th style="padding: 10px; width: 90px; text-align: right;">Price</th>
                                <th style="padding: 10px;">Specs/Notes</th>
                            </tr>
                        </thead>
                        <tbody id="wh-import-list">
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">Type above to
                                    search warehouse stock.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="submitWarehouseImport()" id="btn-submit-wh-import" class="btn-save"
                        style="flex: 2; height: 50px; font-weight: 800; cursor: pointer; border: none; border-radius: 12px;">🚀
                        Import Selected Stock</button>
                    <button type="button" onclick="closeImportModal()" class="btn-cancel"
                        style="flex: 1; height: 50px; font-weight: 700; cursor: pointer; border: none; border-radius: 12px;">Cancel</button>
                </div>
            </div>

            <!-- Tab 3: Work Order AI -->
            <div id="import-tab-workorder-content" style="display: none;">
                <!-- API Key Missing Warning -->
                <div id="workorder-key-warning" style="display: none; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 15px; margin-bottom: 20px; color: #b45309; text-align: left; font-size: 0.85rem;">
                    <div style="font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">⚠️ Gemini API Key Missing</div>
                    Please set your <strong>gemini_api_key</strong> in settings or inside <code>db/config.json</code> before using handwritten AI OCR features.
                </div>

                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px; text-align: left;">
                    Upload or drag & drop a handwritten B2B Work Order image (JPEG/PNG) or PDF. Gemini Vision AI will transcribe and normalize the columns.
                </p>

                <!-- Dropzone Area -->
                <div class="dropzone" id="workorder-dropzone" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: border-color 0.2s, background-color 0.2s;">
                    <svg viewBox="0 0 24 24" style="width: 48px; height: 48px; fill: #94a3b8; margin: 0 auto 10px auto; display: block;">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    <p style="font-weight: 600; margin: 0; color: #475569;">Drag & Drop Work Order Here</p>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 4px 0 0 0;">Supports JPEG, PNG, or PDF files</p>
                    <input type="file" id="workorder-file-input" accept="image/*,application/pdf" style="display: none;">
                </div>

                <!-- Loading overlay -->
                <div id="workorder-loading-overlay" style="display: none; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center;">
                    <div style="border: 4px solid rgba(0, 168, 255, 0.15); border-left-color: var(--accent-color); width: 40px; height: 40px; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;"></div>
                    <style>
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                    <p style="font-weight: 600; margin: 0; color: #475569;">Extracting B2B Work Order details...</p>
                    <p style="font-size: 0.8rem; color: #94a3b8; margin: 4px 0 0 0;">Gemini is running Vision OCR & normalizations...</p>
                </div>

                <!-- Split Stage (Viewer + Editor) -->
                <div id="workorder-stage" style="display: none; display: flex; gap: 20px; margin-bottom: 20px; align-items: stretch; min-height: 400px; height: 50vh;">

                    <!-- Left: Viewer -->
                    <div style="flex: 1.1; border: 1px solid #e2e8f0; border-radius: 12px; background: #09090b; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                        <div id="workorder-viewer-container" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; transform-origin: center; transition: transform 0.2s;">
                            <!-- Populated dynamically -->
                        </div>

                        <!-- Floating controls -->
                        <div style="position: absolute; bottom: 15px; right: 15px; display: flex; gap: 8px; z-index: 10;">
                            <button type="button" onclick="woViewer.rotate(-90)" style="width: 36px; height: 36px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(9,9,11,0.75); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; outline: none; transition: background-color 0.2s;">
                                🔄
                            </button>
                            <button type="button" onclick="woViewer.zoom(0.1)" style="width: 36px; height: 36px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(9,9,11,0.75); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; outline: none; transition: background-color 0.2s;">
                                ➕
                            </button>
                            <button type="button" onclick="woViewer.zoom(-0.1)" style="width: 36px; height: 36px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15); background: rgba(9,9,11,0.75); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; outline: none; transition: background-color 0.2s;">
                                ➖
                            </button>
                        </div>
                    </div>

                    <!-- Right: Editor -->
                    <div style="flex: 1.5; display: flex; flex-direction: column; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; overflow: hidden;">
                        <div style="background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; color: #475569;">Verification Grid</span>
                            <span id="workorder-avg-confidence" style="font-size: 0.75rem; background: #dcfce7; color: #16a34a; padding: 2px 8px; border-radius: 20px; font-weight: 700; border: 1px solid #bbf7d0;">Confidence: 98%</span>
                        </div>

                        <div style="flex: 1; overflow-y: auto;">
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                                <thead>
                                    <tr style="background: #f1f5f9; position: sticky; top: 0; z-index: 10; border-bottom: 1px solid #e2e8f0;">
                                        <th style="padding: 10px; width: 40px; text-align: center;"><input type="checkbox" id="workorder-select-all" checked onchange="woGrid.toggleAll(this)"></th>
                                        <th style="padding: 10px; width: 80px;">Brand</th>
                                        <th style="padding: 10px; width: 100px;">Model</th>
                                        <th style="padding: 10px; width: 80px;">Series</th>
                                        <th style="padding: 10px; width: 70px;">CPU</th>
                                        <th style="padding: 10px;">Description</th>
                                        <th style="padding: 10px; width: 50px; text-align: center;">Qty</th>
                                        <th style="padding: 10px; width: 80px; text-align: right;">Price</th>
                                        <th style="padding: 10px; width: 100px;">Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="workorder-list">
                                    <!-- Populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer Console / Raw OCR -->
                <div id="workorder-console-card" style="display: none; flex-direction: column; gap: 6px; margin-bottom: 20px; text-align: left;">
                    <span style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Raw OCR Output Buffer</span>
                    <pre id="workorder-ocr-console" style="margin: 0; background: #0f172a; color: #38bdf8; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 0.7rem; max-height: 100px; overflow-y: auto; white-space: pre-wrap; border: 1px solid #1e293b;"></pre>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="submitWorkOrderImport()" id="btn-submit-workorder-import" class="btn-save" style="flex: 2; height: 50px; font-weight: 800; cursor: pointer; border: none; border-radius: 12px; display: none;">🚀 Import Verified Items</button>
                    <button type="button" onclick="closeImportModal()" class="btn-cancel" style="flex: 1; height: 50px; font-weight: 700; cursor: pointer; border: none; border-radius: 12px;">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
