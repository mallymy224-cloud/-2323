<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$status = (string) ($_GET['status'] ?? '');
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$statusCounts = ['all' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status') as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
    $statusCounts['all'] += (int) $row['total'];
}

$where = ['1 = 1'];
$params = [];
if (isset(order_statuses()[$status])) {
    $where[] = 'o.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if ($search !== '') {
    $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
     FROM orders o WHERE $whereSql
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'الطلبات';
$pageSubtitle = 'متابعة الطلبات وتحديث حالات التوصيل';
$activePage = 'orders';

$pageParams = [];
if ($search !== '') {
    $pageParams['q'] = $search;
}
if ($status !== '') {
    $pageParams['status'] = $status;
}

$tabs = [
    ''          => 'كل الطلبات',
    'pending'   => 'قيد الانتظار',
    'confirmed' => 'مؤكدة',
    'shipped'   => 'تم الشحن',
    'delivered' => 'تم التسليم',
    'cancelled' => 'ملغاة',
];

require __DIR__ . '/includes/header.php';
?>

<div class="admin-tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <?php
        $tabParams = $statusParams = [];
        if ($search !== '') {
            $tabParams['q'] = $search;
        }
        if ($key !== '') {
            $tabParams['status'] = $key;
        }
        $tabCount = $key === '' ? $statusCounts['all'] : ($statusCounts[$key] ?? 0);
        ?>
        <a class="admin-tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= url('admin/orders.php' . ($tabParams ? '?' . http_build_query($tabParams) : '')) ?>">
            <?= e($label) ?>
            <span><?= $tabCount ?></span>
        </a>
    <?php endforeach; ?>
</div>

<form class="admin-filters" method="get" action="<?= url('admin/orders.php') ?>">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="admin-search">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="رقم الطلب، الاسم أو الهاتف…">
    </div>
    <button class="admin-btn admin-btn-outline" type="submit">بحث</button>
    <?php if ($pageParams): ?><a class="admin-btn admin-btn-ghost" href="<?= url('admin/orders.php') ?>">مسح</a><?php endif; ?>
</form>

<section class="admin-panel admin-panel-flush">
    <?php if ($orders): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>المدينة</th>
                        <th>القطع</th>
                        <th>المجموع</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <a class="admin-order-number" href="<?= url('admin/order-view.php?id=' . (int) $order['id']) ?>" dir="ltr"><?= e($order['order_number']) ?></a>
                            </td>
                            <td>
                                <span class="admin-cell-title"><?= e($order['customer_name']) ?></span>
                                <span class="admin-cell-sub" dir="ltr"><?= e($order['phone']) ?></span>
                            </td>
                            <td><?= e($order['city']) ?></td>
                            <td><?= (int) $order['items_count'] ?></td>
                            <td><strong><?= money($order['total']) ?></strong></td>
                            <td><?= status_badge($order['status']) ?></td>
                            <td><?= ar_date($order['created_at']) ?></td>
                            <td>
                                <a class="admin-btn admin-btn-sm admin-btn-outline" href="<?= url('admin/order-view.php?id=' . (int) $order['id']) ?>">عرض</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination-wrap">
            <?= pagination_links($page, $pages, $pageParams) ?>
        </div>
    <?php else: ?>
        <div class="admin-empty admin-empty-large">
            <span class="admin-empty-icon">📦</span>
            <h3>لا توجد طلبات مطابقة</h3>
            <p>ستظهر الطلبات الجديدة هنا تلقائياً.</p>
            <a class="admin-btn admin-btn-outline" href="<?= url('admin/orders.php') ?>">عرض كل الطلبات</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
