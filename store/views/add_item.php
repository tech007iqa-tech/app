<?php
// views/add_item.php
// Wrapper that renders the manual intake card only for active Store Tenders
require_once __DIR__ . '/../core/Tender.php';

if (Tender::isTenderMode()) {
    require __DIR__ . '/tender/add_item_tender.php';
}
