<?php
require_once __DIR__ . '/functions.php';

$currentPage    = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$cartCount      = cart_count();
$user           = current_user();
$navCategories  = all_categories();
$storeName      = setting('store_name', 'مراكشية');
$pageTitle      = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' | ' . $storeName : $storeName . ' — ' . setting('tagline', 'أناقة مغربية أصيلة');
$metaDescription = $metaDescription ?? setting('about_text', 'متجر ملابس مغربي أصيل.');
$isActive = static function (string $file) use ($currentPage): string {
    return $currentPage === $file ? ' is-active' : '';
};
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e(excerpt($metaDescription, 160)) ?>">
    <link rel="icon" href="<?= e(site_icon()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

<div class="topbar">
    <div class="container topbar-inner">
        <p class="topbar-note">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>
            <span><?= e(setting('announcement', 'توصيل مجاني للطلبات فوق 500 د.م.')) ?></span>
        </p>
        <div class="topbar-links">
            <a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone', '+212600000000'))) ?>">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 5c0 8 6 14 15 14l1-3-5-2-2 2c-3-1-5-3-6-6l2-2-2-5z"/></svg>
                <?= e(setting('phone', '+212 6 12 34 56 78')) ?>
            </a>
            <span class="topbar-sep" aria-hidden="true"></span>
            <a href="<?= e(setting('instagram', '#')) ?>" target="_blank" rel="noopener">إنستغرام</a>
        </div>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <button class="nav-toggle" id="navToggle" type="button" aria-label="فتح القائمة" aria-expanded="false" aria-controls="mobileNav">
            <span></span><span></span><span></span>
        </button>

        <a class="brand" href="<?= url('index.php') ?>" aria-label="<?= e($storeName) ?> — الصفحة الرئيسية">
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

        <nav class="main-nav" aria-label="التنقل الرئيسي">
            <a class="nav-link<?= $isActive('index.php') ?>" href="<?= url('index.php') ?>">الرئيسية</a>
            <a class="nav-link<?= $isActive('shop.php') ?>" href="<?= url('shop.php') ?>">المتجر</a>
            <div class="nav-dropdown">
                <button class="nav-link nav-drop-btn" type="button" aria-haspopup="true">
                    التصنيفات
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($navCategories as $category): ?>
                        <a href="<?= url('shop.php?category=' . (int) $category['id']) ?>">
                            <span><?= e($category['name']) ?></span>
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a class="nav-link" href="<?= url('shop.php?sort=newest') ?>">وصل حديثاً</a>
            <a class="nav-link" href="<?= url('shop.php?featured=1') ?>">مختاراتنا</a>
        </nav>

        <div class="header-actions">
            <form class="header-search" role="search" action="<?= url('shop.php') ?>" method="get">
                <?= search_icon_html() ?>
                <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="ابحث عن قطعة…" aria-label="بحث في المتجر">
            </form>

            <a class="icon-btn account-btn" href="<?= is_logged_in() ? url('account.php') : url('login.php') ?>">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20c1.5-4 4.3-5.5 7.5-5.5s6 1.5 7.5 5.5"/></svg>
                <span class="icon-label"><?= is_logged_in() ? 'حسابي' : 'دخول' ?></span>
            </a>

            <a class="icon-btn cart-btn" href="<?= url('cart.php') ?>" aria-label="سلة التسوق">
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 8h14l-1.2 11H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                <span class="icon-label">السلة</span>
                <span class="cart-badge" id="cartCount" data-count="<?= (int) $cartCount ?>"><?= (int) $cartCount ?></span>
            </a>
        </div>
    </div>

    <div class="mobile-nav" id="mobileNav" hidden>
        <form class="mobile-search" role="search" action="<?= url('shop.php') ?>" method="get">
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="ابحث عن قطعة…" aria-label="بحث في المتجر">
            <button type="submit" aria-label="بحث">
                <?= search_icon_html() ?>
            </button>
        </form>
        <a href="<?= url('index.php') ?>">الرئيسية</a>
        <a href="<?= url('shop.php') ?>">كل المنتجات</a>
        <?php foreach ($navCategories as $category): ?>
            <a href="<?= url('shop.php?category=' . (int) $category['id']) ?>"><?= e($category['name']) ?></a>
        <?php endforeach; ?>
        <a href="<?= url('shop.php?featured=1') ?>">مختاراتنا</a>
        <a href="<?= is_logged_in() ? url('account.php') : url('login.php') ?>"><?= is_logged_in() ? 'حسابي' : 'تسجيل الدخول' ?></a>
    </div>
</header>

<main class="site-main">
<?php $flashHtml = render_flashes(); ?>
<?php if ($flashHtml !== ''): ?>
    <div class="container flash-wrap"><?= $flashHtml ?></div>
<?php endif; ?>
