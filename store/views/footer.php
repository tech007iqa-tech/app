<?php
// views/footer.php
?>
    <footer class="store-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="logos" style="transform: scale(0.95); transform-origin: left center;">
                    <span class="logos-title"><span>IQA</span> Metal</span>
                    <small class="logos-tagline">Warehouse Hardware &amp; Electronics</small>
                </div>
                <p class="footer-desc">
                    Certified refurbished computers, enterprise hardware, and clearance electronics inspected by certified technicians.
                </p>
            </div>
            <div class="footer-links">
                <a href="category.php?cat=laptops">Laptops</a>
                <a href="category.php?cat=desktops">Desktops</a>
                <a href="category.php?cat=servers">Servers</a>
                <a href="category.php?cat=parts">Parts</a>
                <a href="terms.php">Terms of Sale</a>
                <a href="https://iqametal.com" target="_blank" rel="noopener">IQAMetal.com &nearr;</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> IQA Metal. All rights reserved. By purchasing, you agree to our <a href="terms.php">Terms of Sale and No Return Policy</a>.</p>
        </div>
    </footer>

    <?php require __DIR__ . '/product_modal.php'; ?>
</body>
</html>
