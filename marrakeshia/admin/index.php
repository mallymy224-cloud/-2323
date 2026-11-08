<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$pageTitle = 'نظرة عامة';
$pageSubtitle = 'ملخص سريع لأداء متجرك اليوم';
$activePage = 'dashboard';

$stats = [
    'products'  => (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'active'    => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'orders'    => (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending'   => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'customers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'revenue'   => (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'cancelled'")->fetchColumn(),
    'lowstock'  => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 3 AND status = 'active'")->fetchColumn(),
];

$recentOrders = $pdo->query(
    'SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 7'
)->fetchAll();

$lowStock = $pdo->query(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.stock <= 3 AND p.status = 'active'
     ORDER BY p.stock ASC, p.id DESC LIMIT 5"
)->fetchAll();

$topProducts = $pdo->query(
    "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS sold, SUM(oi.price * oi.quantity) AS revenue
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled'
     GROUP BY oi.product_id, oi.product_name
     ORDER BY sold DESC, revenue DESC
     LIMIT 5"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="admin-stats-grid">
    <a class="admin-stat-card" href="<?= url('admin/orders.php') ?>">
        <span class="admin-stat-icon is-clay">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 8 4v10l-8 4-8-4V7l8-4Z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
        </span>
        <div class="admin-stat-body">
            <span>إجمالي الطلبات</span>
            <strong><?= $stats['orders'] ?></strong>
            <small><?= $stats['pending'] ?> طلب قيد الانتظار</small>
        </div>
    </a>

    <div class="admin-stat-card">
        <span class="admin-stat-icon is-gold">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 10h.01M18 14h.01"/></svg>
        </span>
        <div class="admin-stat-body">
            <span>إجمالي المبيعات</span>
            <strong><?= money($stats['revenue']) ?></strong>
            <small>الطلبات غير الملغاة</small>
        </div>
    </div>

    <a class="admin-stat-card" href="<?= url('admin/products.php') ?>">
        <span class="admin-stat-icon is-olive">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 8h14l-1.2 11H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
        </span>
        <div class="admin-stat-body">
            <span>المنتجات</span>
            <strong><?= $stats['products'] ?></strong>
            <small><?= $stats['active'] ?> منتج منشور</small>
        </div>
    </a>

    <a class="admin-stat-card" href="<?= url('admin/customers.php') ?>">
        <span class="admin-stat-icon is-plum">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-4 3.5-5.5 6.5-5.5s5.5 1.5 6.5 5.5"/><path d="M16 5a3.5 3.5 0 0 1 0 7"/></svg>
        </span>
        <div class="admin-stat-body">
            <span>العملاء</span>
            <strong><?= $stats['customers'] ?></strong>
            <small>حسابات مسجلة</small>
        </div>
    </a>
</div>

<div class="admin-quick-actions">
    <a class="admin-quick-btn" href="<?= url('admin/product-form.php') ?>">
        <span>＋</span> إضافة منتج جديد
    </a>
    <a class="admin-quick-btn" href="<?= url('admin/orders.php?status=pending') ?>">
        <span>◷</span> الطلبات المعلقة (<?= $stats['pending'] ?>)
    </a>
    <a class="admin-quick-btn" href="<?= url('admin/sizes.php') ?>">
        <span>⊞</span> إضافة المقاسات المتاحة
    </a>
    <a class="admin-quick-btn" href="<?= url('admin/categories.php') ?>">
        <span>◈</span> إدارة التصنيفات
    </a>
    <a class="admin-quick-btn" href="<?= url('admin/settings.php') ?>">
        <span>⚙</span> إعدادات المتجر
    </a>
</div>

<div class="admin-grid-2">
    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2>أحدث الطلبات</h2>
                <p>آخر الطلبات الواردة إلى المتجر</p>
            </div>
            <a class="admin-link" href="<?= url('admin/orders.php') ?>">كل الطلبات ←</a>
        </div>

        <?php if ($recentOrders): ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>العميل</th>
                            <th>المجموع</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong dir="ltr"><?= e($order['order_number']) ?></strong></td>
                                <td>
                                    <span class="admin-cell-title"><?= e($order['customer_name']) ?></span>
                                    <span class="admin-cell-sub" dir="ltr"><?= e($order['phone']) ?></span>
                                </td>
                                <td><?= money($order['total']) ?></td>
                                <td><?= status_badge($order['status']) ?></td>
                                <td><?= ar_date($order['created_at']) ?></td>
                                <td><a class="admin-icon-link" href="<?= url('admin/order-view.php?id=' . (int) $order['id']) ?>" aria-label="عرض الطلب">↗</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty"><p>لا توجد طلبات بعد.</p></div>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2>مخزون منخفض</h2>
                <p>منتجات تحتاج إعادة تعبئة</p>
            </div>
            <a class="admin-link" href="<?= url('admin/products.php') ?>">المنتجات ←</a>
        </div>

        <?php if ($lowStock): ?>
            <ul class="admin-stock-list">
                <?php foreach ($lowStock as $item): ?>
                    <li>
                        <img src="<?= product_image($item['image']) ?>" alt="<?= e($item['name']) ?>" width="46" height="56" loading="lazy">
                        <div class="admin-stock-info">
                            <strong><?= e($item['name']) ?></strong>
                            <small><?= e($item['category_name'] ?? 'بدون تصنيف') ?></small>
                        </div>
                        <span class="admin-stock-count <?= (int) $item['stock'] === 0 ? 'is-out' : '' ?>">
                            <?= (int) $item['stock'] === 0 ? 'نفد' : (int) $item['stock'] . ' قطع' ?>
                        </span>
                        <a class="admin-icon-link" href="<?= url('admin/product-form.php?id=' . (int) $item['id']) ?>" aria-label="تعديل">✎</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="admin-empty"><p>كل المنتجات متوفرة بكميات جيدة. 👌</p></div>
        <?php endif; ?>
    </section>
</div>

<section class="admin-panel">
    <div class="admin-panel-head">
        <div>
            <h2>الأكثر مبيعاً</h2>
            <p>أفضل المنتجات أداءً حسب الكميات المطلوبة</p>
        </div>
    </div>

    <?php if ($topProducts): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>المنتج</th><th>الكميات المباعة</th><th>الإيرادات</th><th>المساهمة</th></tr>
                </thead>
                <tbody>
                    <?php
                    $maxSold = max(array_map(static function ($row) { return (int) $row['sold']; }, $topProducts));
                    foreach ($topProducts as $row):
                        $percent = $maxSold > 0 ? (int) round(((int) $row['sold'] / $maxSold) * 100) : 0;
                    ?>
                        <tr>
                            <td><span class="admin-cell-title"><?= e($row['product_name']) ?></span></td>
                            <td><?= (int) $row['sold'] ?></td>
                            <td><?= money($row['revenue']) ?></td>
                            <td>
                                <span class="admin-bar"><span style="width: <?= $percent ?>%"></span></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="admin-empty"><p>لا توجد مبيعات بعد.</p></div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
