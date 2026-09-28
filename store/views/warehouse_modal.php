<?php
// views/warehouse_modal.php
// Wrapper that renders the warehouse stock drawer for active Store Tenders
require_once __DIR__ . '/../core/Tender.php';

if (Tender::isTenderMode()) {
    require __DIR__ . '/tender/warehouse_drawer.php';
}
