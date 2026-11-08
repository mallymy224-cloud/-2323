<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$search = trim((string) ($_GET['q'] ?? ''));
$where = "u.role = 'customer'";
$params = [];
if ($search !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$stmt = $pdo->prepare(
    "SELECT u.*,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders_count,
        (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS total_spent
     FROM users u
     WHERE $where
     ORDER BY u.created_at DESC, u.id DESC"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totalCustomers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'cancelled'")->fetchColumn();

$pageTitle = 'العملاء';
$pageSubtitle = 'قائمة الحسابات المسجلة ونشاطها الشرائي';
$activePage = 'customers';

require __DIR__ . '/includes/header.php';
?>

<div class="admin-stats-grid admin-stats-grid-3">
    <div class="admin-stat-card">
        <span class="admin-stat-icon is-plum">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-4 3.5-5.5 6.5-5.5s5.5 1.5 6.5 5.5"/><path d="M16 5a3.5 3.5 0 0 1 0 7"/></svg>
        </span>
        <div class="admin-stat-body"><span>العملاء المسجلون</span><strong><?= $totalCustomers ?></strong><small>حسابات المتجر</small></div>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-icon is-clay">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 8 4v10l-8 4-8-4V7l8-4Z"/><path d="M4 7l8 4 8-4M12 11v10"/></svg>
        </span>
        <div class="admin-stat-body"><span>إجمالي الطلبات</span><strong><?= $totalOrders ?></strong><small>من جميع العملاء</small></div>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-icon is-gold">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
        </span>
        <div class="admin-stat-body"><span>إجمالي المبيعات</span><strong><?= money($totalRevenue) ?></strong><small>الطلبات غير الملغاة</small></div>
    </div>
</div>

<form class="admin-filters" method="get" action="<?= url('admin/customers.php') ?>">
    <div class="admin-search">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="الاسم، البريد أو الهاتف…">
    </div>
    <button class="admin-btn admin-btn-outline" type="submit">بحث</button>
    <?php if ($search !== ''): ?><a class="admin-btn admin-btn-ghost" href="<?= url('admin/customers.php') ?>">مسح</a><?php endif; ?>
</form>

<section class="admin-panel admin-panel-flush">
    <?php if ($customers): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>الهاتف</th>
                        <th>المدينة</th>
                        <th>الطلبات</th>
                        <th>إجمالي الشراء</th>
                        <th>تاريخ التسجيل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <div class="admin-customer-cell">
                                    <span class="admin-avatar"><?= e(mb_substr($customer['name'], 0, 1, 'UTF-8')) ?></span>
                                    <div>
                                        <strong><?= e($customer['name']) ?></strong>
                                        <small dir="ltr"><?= e($customer['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td dir="ltr"><?= e($customer['phone'] ?: '—') ?></td>
                            <td><?= e($customer['city'] ?: '—') ?></td>
                            <td><span class="admin-stock-pill is-ok"><?= (int) $customer['orders_count'] ?></span></td>
                            <td><strong><?= money($customer['total_spent']) ?></strong></td>
                            <td><?= ar_date($customer['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="admin-empty admin-empty-large">
            <span class="admin-empty-icon">👤</span>
            <h3>لا يوجد عملاء</h3>
            <p>عند تسجيل العملاء في المتجر ستظهر حساباتهم هنا.</p>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
