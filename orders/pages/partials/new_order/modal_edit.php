<?php
/**
 * Edit Item Modal Partial
 * Dialog modal for modifying item specifications, pricing, quantities, and notes.
 */
?>
<!-- Edit Modal -->
<div id="editModal" class="modal-overlay" style="display:none;" onclick="if(event.target === this) closeEditModal()">
    <div class="modal-card">
        <h3>Edit Item</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="update_id" id="edit-id">
            <?= UI::csrf_field() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit-brand">Brand</label>
                    <input type="text" name="update_brand" id="edit-brand" list="brand-options" required>
                </div>
                <div class="form-group">
                    <label for="edit-model">Model</label>
                    <input type="text" name="update_model" id="edit-model" list="edit-model-options" required>
                    <datalist id="edit-model-options"></datalist>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1.2;">
                    <label for="edit-series">Series</label>
                    <input type="text" name="update_series" id="edit-series" list="edit-series-options">
                    <datalist id="edit-series-options"></datalist>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="edit-cpu-series">CPU</label>
                    <select id="edit-cpu-series" name="edit_cpu_series" style="width: 100%;">
                        <option value="" disabled selected hidden>e.g. i5</option>
                        <option value=""></option>
                        <option value="i3">i3</option>
                        <option value="i5">i5</option>
                        <option value="i7">i7</option>
                        <option value="i9">i9</option>
                        <option value="Ryzen 2">Ryzen 2</option>
                        <option value="Ryzen 3">Ryzen 3</option>
                        <option value="Ryzen 5">Ryzen 5</option>
                        <option value="Ryzen 7">Ryzen 7</option>
                        <option value="Ryzen 9">Ryzen 9</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="edit-cpu-gen">GEN</label>
                    <input type="text" name="edit_cpu_gen" id="edit-cpu-gen" list="cpu-gen-options"
                        placeholder="e.g. 8th">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label for="edit-desc">Description</label>
                    <textarea name="update_desc" id="edit-desc"></textarea>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="edit-notes">Notes</label>
                    <textarea name="update_notes" id="edit-notes"></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit-qty">Quantity</label>
                    <input type="number" name="update_qty" id="edit-qty" step="any" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit-price">Price</label>
                    <input type="number" name="update_price" id="edit-price" step="0.01">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>
