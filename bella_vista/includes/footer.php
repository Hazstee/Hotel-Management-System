    </main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div>
            <div class="footer-brand">Bella Vista</div>
            <p class="footer-brand-subtitle">HOTEL &amp; EVENTS PLACE</p>
            <p class="footer-tagline">A quieter kind of luxury.</p>
        </div>
        <div class="footer-details">
            <p>Open Monday to Sunday</p>
            <p>Check-in <?= e(CHECK_IN_TIME) ?>, Check-out <?= e(CHECK_OUT_TIME) ?></p>
            <p>At the foothills of the mountains, Philippines.</p>
            <a class="admin-login-link" href="admin/login.php">Admin login</a>
        </div>
    </div>
    <div class="footer-bottom">&copy; <?= date('Y') ?> Bella Vista Hotel &amp; Events Place</div>
</footer>
<?php foreach (($page_scripts ?? []) as $script): ?>
<script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
