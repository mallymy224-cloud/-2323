<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$admin = current_user();
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'لوحة التحكم';
$pageSubtitle = $pageSubtitle ?? '';
$pendingOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

$menu = [
    'dashboard'  => ['label' => 'نظرة عامة', 'icon' => 'grid', 'url' => url('admin/index.php'), 'file' => 'index.php'],
    'products'   => ['label' => 'المنتجات', 'icon' => 'bag', 'url' => url('admin/products.php'), 'file' => 'products.php'],
    'sizes'      => ['label' => 'المقاسات', 'icon' => 'ruler', 'url' => url('admin/sizes.php'), 'file' => 'sizes.php'],
    'categories' => ['label' => 'التصنيفات', 'icon' => 'layers', 'url' => url('admin/categories.php'), 'file' => 'categories.php'],
    'orders'     => ['label' => 'الطلبات', 'icon' => 'box', 'url' => url('admin/orders.php'), 'file' => 'orders.php'],
    'customers'  => ['label' => 'العملاء', 'icon' => 'users', 'url' => url('admin/customers.php'), 'file' => 'customers.php'],
    'settings'   => ['label' => 'الإعدادات', 'icon' => 'settings', 'url' => url('admin/settings.php'), 'file' => 'settings.php'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | لوحة تحكم <?= e(setting('store_name', 'مراكشية')) ?></title>
    <link rel="icon" href="<?= e(site_icon()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-brand">
            <span class="admin-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><path d="M24 4c7 7 12 14 12 22a12 12 0 0 1-24 0C12 18 17 11 24 4Z"/><path d="M24 14v20M18 24h12"/></svg>
            </span>
            <div>
                <strong><?= e(setting('store_name', 'مراكشية')) ?></strong>
                <span>لوحة التحكم</span>
            </div>
        </div>

        <nav class="admin-nav" aria-label="قائمة لوحة التحكم">
            <span class="admin-nav-label">إدارة المتجر</span>
            <?php foreach ($menu as $key => $item): ?>
                <a class="admin-nav-link<?= $activePage === $key ? ' is-active' : '' ?>" href="<?= $item['url'] ?>">
                    <?php if ($item['icon'] === 'grid'): ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    <?php elseif ($item['icon'] === 'bag'): ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 8h14l-1.2 11H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    <?php elseif ($item['icon'] === 'ruler'): ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8h18v8H3z"/><path d="M7 8v3M11 8v5M15 8v3M19 8v5"/></svg>
                    <?php elseif ($item['icon'] === 'layers'): ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/></svg>
                    <?php elseif ($item['icon'] === 'box'): ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 8 4v10l-8 4-8-4V7l8-4Z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
                    <?php elseif ($item['icon'] === 'users'): ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-4 3.5-5.5 6.5-5.5s5.5 1.5 6.5 5.5"/><path d="M16 5a3.5 3.5 0 0 1 0 7M18 14c2 .6 3.3 2.2 4 6"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/></svg>
                    <?php endif; ?>
                    <span><?= e($item['label']) ?></span>
                    <?php if ($key === 'orders' && $pendingOrders > 0): ?>
                        <span class="admin-nav-badge"><?= $pendingOrders ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-foot">
            <a class="admin-nav-link" href="<?= url('index.php') ?>" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 3h7v7M21 3l-9 9"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg>
                <span>عرض المتجر</span>
            </a>
            <a class="admin-nav-link is-danger" href="<?= url('logout.php') ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>
                <span>تسجيل الخروج</span>
            </a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-start">
                <button class="admin-burger" id="adminBurger" type="button" aria-label="فتح القائمة" aria-expanded="false" aria-controls="adminSidebar">
                    <span></span><span></span><span></span>
                </button>
                <div>
                    <h1><?= e($pageTitle) ?></h1>
                    <?php if ($pageSubtitle !== ''): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="admin-topbar-end">
                <a class="admin-topbar-link" href="<?= url('index.php') ?>" target="_blank" rel="noopener">المتجر</a>
                <span class="admin-user">
                    <span class="admin-avatar"><?= e(mb_substr($admin['name'] ?? 'م', 0, 1, 'UTF-8')) ?></span>
                    <span class="admin-user-info"><strong><?= e($admin['name'] ?? 'المدير') ?></strong><small>مدير المتجر</small></span>
                </span>
            </div>
        </header>

        <div class="admin-content">
            <?php $flashHtml = render_flashes(); ?>
            <?php if ($flashHtml !== ''): ?>
                <div class="admin-flashes"><?= $flashHtml ?></div>
            <?php endif; ?>
