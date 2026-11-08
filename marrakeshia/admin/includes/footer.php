        </div>

        <footer class="admin-footer">
            <p>© <?= date('Y') ?> <?= e(setting('store_name', 'مراكشية')) ?> — لوحة التحكم</p>
            <p>صُنع بـ PHP &amp; MySQL</p>
        </footer>
    </div>
</div>

<div class="admin-overlay" id="adminOverlay" hidden></div>

<script src="<?= url('assets/js/admin.js') ?>" defer></script>
<?php if (!empty($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
