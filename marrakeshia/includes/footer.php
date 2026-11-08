<?php
$footerCategories = all_categories();
$storeName = setting('store_name', 'مراكشية');
?>
</main>

<footer class="site-footer">
    <div class="footer-pattern" aria-hidden="true"></div>
    <div class="container footer-grid">
        <div class="footer-col footer-about">
            <a class="brand brand-footer" href="<?= url('index.php') ?>">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M24 4c7 7 12 14 12 22a12 12 0 0 1-24 0C12 18 17 11 24 4Z"/>
                        <path d="M24 14v20M18 24h12"/>
                    </svg>
                </span>
                <span class="brand-text">
                    <span class="brand-name"><?= e($storeName) ?></span>
                    <span class="brand-tag">MARRAKESHIA</span>
                </span>
            </a>
            <p><?= e(setting('about_text', 'متجر مغربي يحتفي بالأناقة الأصيلة.')) ?></p>
            <div class="social-links">
                <a href="<?= e(setting('instagram', '#')) ?>" target="_blank" rel="noopener" aria-label="إنستغرام">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/></svg>
                </a>
                <a href="<?= e(setting('facebook', '#')) ?>" target="_blank" rel="noopener" aria-label="فيسبوك">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v6h4v-6h3l1-4h-4V9c0-.6.4-1 1-1Z"/></svg>
                </a>
                <a href="https://wa.me/<?= e(setting('whatsapp', '212600000000')) ?>" target="_blank" rel="noopener" aria-label="واتساب">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M21 12a9 9 0 0 1-13.4 7.8L3 21l1.2-4.5A9 9 0 1 1 21 12Z"/><path d="M8.5 9c.5 3 2.5 5 5.5 5.5"/></svg>
                </a>
            </div>
        </div>

        <div class="footer-col">
            <h3>روابط سريعة</h3>
            <ul class="footer-links">
                <li><a href="<?= url('index.php') ?>">الرئيسية</a></li>
                <li><a href="<?= url('shop.php') ?>">كل المنتجات</a></li>
                <li><a href="<?= url('shop.php?featured=1') ?>">مختاراتنا</a></li>
                <li><a href="<?= url('cart.php') ?>">سلة التسوق</a></li>
                <li><a href="<?= is_logged_in() ? url('account.php') : url('login.php') ?>"><?= is_logged_in() ? 'حسابي' : 'تسجيل الدخول' ?></a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3>التصنيفات</h3>
            <ul class="footer-links">
                <?php foreach ($footerCategories as $category): ?>
                    <li><a href="<?= url('shop.php?category=' . (int) $category['id']) ?>"><?= e($category['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="footer-col">
            <h3>تواصلي معنا</h3>
            <ul class="footer-contact">
                <li>
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <span><?= e(setting('address', 'مراكش، المغرب')) ?></span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 5c0 8 6 14 15 14l1-3-5-2-2 2c-3-1-5-3-6-6l2-2-2-5z"/></svg>
                    <a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone', '+212600000000'))) ?>" dir="ltr"><?= e(setting('phone', '+212 6 12 34 56 78')) ?></a>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                    <a href="mailto:<?= e(setting('email', 'hello@marrakeshia.ma')) ?>" dir="ltr"><?= e(setting('email', 'hello@marrakeshia.ma')) ?></a>
                </li>
            </ul>
            <div class="footer-pay">
                <?php foreach (enabled_payment_methods() as $info): ?>
                    <span class="pay-chip"><?= e($info['label']) ?></span>
                <?php endforeach; ?>
                <span class="pay-chip">توصيل لكل المغرب</span>
            </div>
        </div>
    </div>

    <div class="container footer-bottom">
        <p>© <?= date('Y') ?> <?= e($storeName) ?> — جميع الحقوق محفوظة.</p>
        <p class="footer-made">صُنع بحب في مراكش <span aria-hidden="true">✦</span></p>
    </div>
</footer>

<button class="back-to-top" id="backToTop" type="button" aria-label="العودة إلى الأعلى">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
</button>

<div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="true"></div>

<script src="<?= url('assets/js/main.js') ?>" defer></script>
<?php if (!empty($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
