<?php
include 'core/auth.php'; // Session is already started and checked
?>
<style>
.container.order-view {
    padding: 0 !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    max-width: 100% !important;
    width: 100% !important;
}
.footer {
    display: none !important;
}
</style>

<div class="inbound-iframe-container" style="width: 100%; margin: 0; padding: 0; height: calc(100vh - 120px); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-md);">
    <iframe src="../sampleWHdata/audit.html" style="width: 100%; height: 100%; border: none; display: block; background: #09090b;" allow="camera; clipboard-read; clipboard-write"></iframe>
</div>
