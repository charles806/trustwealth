<?php
declare(strict_types=1);

/* $scripts: array of extra JS files (main.js is always loaded). */
if (!isset($scripts)) {
    $scripts = [];
}
?>
    <!-- $base: site-root prefix so /admin/ pages resolve links correctly. -->
<?php $base ??= str_repeat('../', count(array_filter(explode('/', trim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'))))); ?>
    <footer class="footer" id="contact">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="logo" href="<?= $base ?>index.php" aria-label="<?= e(APP_NAME) ?> — home">
                    <span class="mark"></span>Trust<span class="muted">&nbsp;Wealth</span>
                </a>
                <p>A fiduciary wealth-management platform trading Bitcoin and USDT with years of steady market experience behind it.</p>
            </div>
            <div class="footer-col">
                <h3>Services</h3>
                <ul>
                    <li><a href="<?= $base ?>signup.php">Buy Bitcoin</a></li>
                    <li><a href="<?= $base ?>signup.php">Buy USDT</a></li>
                    <li><a href="<?= $base ?>signup.php">Sell Bitcoin</a></li>
                    <li><a href="<?= $base ?>signup.php">Sign up</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Information</h3>
                <ul>
                    <li><a href="<?= $base ?>index.php#about">About us</a></li>
                    <li><a href="<?= $base ?>index.php#plans">Pricing</a></li>
                    <li><a href="<?= $base ?>index.php#how">Getting started</a></li>
                    <li><a href="<?= $base ?>index.php#payment">Payment options</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Get in touch</h3>
                <ul class="contact">
                    <li><i class="fa-solid fa-location-dot"></i> Muhlenstrasse 38, Regensburg, Germany</li>
                    <li><i class="fa-solid fa-envelope"></i> <a href="mailto:obiezedavis468@gmail.com">obiezedavis468@gmail.com</a></li>
                    <li><i class="fa-solid fa-globe"></i> <a href="http://btcsitec09.com" rel="noopener">btcsitec09.com</a></li>
                    <li><i class="fa-solid fa-phone"></i> <a href="tel:+17722330349">+1 (772) 233-0349</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &#8212; All rights reserved</div>
    </footer>

    <script src="<?= $base ?>main.js"></script>
    <?php foreach ($scripts as $js) : ?>
        <script src="<?= $base ?><?= e($js) ?>"></script>
    <?php endforeach; ?>
</body>
</html>