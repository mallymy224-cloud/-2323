<?php
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    flash('info', 'يرجى تسجيل الدخول للوصول إلى حسابك.');
    redirect(url('login.php'));
}

$user = current_user();

$ordersStmt = db()->prepare(
    'SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC'
);
$ordersStmt->execute([(int) $user['id']]);
$orders = $ordersStmt->fetchAll();

$totalSpent = 0.0;
foreach ($orders as $order) {
    if ($order['status'] !== 'cancelled') {
        $totalSpent += (float) $order['total'];
    }
}

$pageTitle = 'حسابي';
require __DIR__ . '/includes/header.php';
?>

<div class="page-hero page-hero-slim">
    <div class="container">
        <nav class="breadcrumb" aria-label="مسار التنقل">
            <a href="<?= url('index.php') ?>">الرئيسية</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">حسابي</span>
        </nav>
        <h1>مرحباً، <?= e($user['name']) ?></h1>
        <p>من هنا تتابعين طلباتك وتديرين معلوماتك الشخصية.</p>
    </div>
</div>

<div class="container section account-section">
    <div class="account-layout">
        <aside class="account-side">
            <div class="account-card">
                <span class="account-avatar"><?= e(mb_substr($user['name'], 0, 1, 'UTF-8')) ?></span>
                <h2><?= e($user['name']) ?></h2>
                <p dir="ltr"><?= e($user['email']) ?></p>
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="btn btn-outline btn-block" href="<?= url('admin/index.php') ?>">لوحة التحكم</a>
                <?php endif; ?>
                <a class="btn btn-ghost btn-block" href="<?= url('logout.php') ?>">تسجيل الخروج</a>
            </div>

            <div class="account-card">
                <h3>معلوماتي</h3>
                <ul class="info-list info-list-sm">
                    <li><span>الهاتف</span><strong dir="ltr"><?= e($user['phone'] ?: '—') ?></strong></li>
                    <li><span>المدينة</span><strong><?= e($user['city'] ?: '—') ?></strong></li>
                    <li><span>العنوان</span><strong><?= e($user['address'] ?: '—') ?></strong></li>
                    <li><span>عضوة منذ</span><strong><?= ar_date($user['created_at']) ?></strong></li>
                </ul>
                <p class="account-hint">تُحدّث هذه المعلومات تلقائياً عند إتمام أي طلب.</p>
            </div>
        </aside>

        <div class="account-main">
            <div class="account-stats">
                <div class="stat-card">
                    <span class="stat-icon">🛍️</span>
                    <div><strong><?= count($orders) ?></strong><small>طلب</small></div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">💳</span>
                    <div><strong><?= money($totalSpent) ?></strong><small>إجمالي المشتريات</small></div>
                </div>
                <div class="stat-card">
                    <span class="stat-icon">✦</span>
                    <div><strong><?= e(setting('store_name', 'مراكشية')) ?></strong><small>عضوة مميزة</small></div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>طلباتي</h2>
                    <a class="link-more" href="<?= url('shop.php') ?>">تسوق جديد
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    </a>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-state">
                        <span class="empty-icon" aria-hidden="true">✦</span>
                        <h3>لا توجد طلبات بعد</h3>
                        <p>ابدئي التسوق لتظهر طلباتك هنا.</p>
                        <a class="btn btn-primary" href="<?= url('shop.php') ?>">اكتشفي التشكيلة</a>
                    </div>
                <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="order-card">
                                <div class="order-card-main">
                                    <div>
                                        <span class="order-label">رقم الطلب</span>
                                        <strong dir="ltr"><?= e($order['order_number']) ?></strong>
                                    </div>
                                    <div>
                                        <span class="order-label">التاريخ</span>
                                        <strong><?= ar_date($order['created_at']) ?></strong>
                                    </div>
                                    <div>
                                        <span class="order-label">القطع</span>
                                        <strong><?= (int) $order['items_count'] ?></strong>
                                    </div>
                                    <div>
                                        <span class="order-label">الإجمالي</span>
                                        <strong><?= money($order['total']) ?></strong>
                                    </div>
                                    <div>
                                        <span class="order-label">الحالة</span>
                                        <?= status_badge($order['status']) ?>
                                    </div>
                                </div>
                                <a class="btn btn-outline btn-sm" href="<?= url('account-order.php?id=' . (int) $order['id']) ?>">تفاصيل الطلب</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
