<?php
/**
 * New Order / Batch Builder (Main Orchestrator)
 * Modularized view coordinating Order Banner, In-Place Editable Spreadsheet Table, Edit Item Modal, and Batch Importer Modal.
 */

// 1. Process Actions (Form submission, customer profile, and order line items)
require_once __DIR__ . '/partials/new_order/actions.php';
?>

<div class="new-order-layout spreadsheet-mode">
    <!-- 2. Top Horizontal Summary Banner Card -->
    <?php include __DIR__ . '/partials/new_order/banner.php'; ?>

    <!-- 3. Main Content: Spreadsheet Editable Table -->
    <?php include __DIR__ . '/partials/new_order/spreadsheet_table.php'; ?>
</div>

<!-- 4. Dialog Modals -->
<?php include __DIR__ . '/partials/new_order/modal_edit.php'; ?>
<?php include __DIR__ . '/partials/new_order/modal_import.php'; ?>