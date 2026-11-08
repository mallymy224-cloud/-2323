<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', 'تم حذف المنتج بنجاح.');
        redirect(url('admin/products.php'));
    }

    if ($action === 'toggle_featured' && $id > 0) {
        $pdo->prepare('UPDATE products SET featured = 1 - featured WHERE id = ?')->execute([$id]);
        flash('success', 'تم تحديث حالة التمييز.');
        redirect(url('admin/products.php'));
    }

    if ($action === 'toggle_status' && $id > 0) {
        $pdo->prepare("UPDATE products SET status = IF(status = 'active', 'draft', 'active') WHERE id = ?")->execute([$id]);
        flash('success', 'تم تحديث حالة نشر المنتج.');
        redirect(url('admin/products.php'));
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category'] ?? 0);
$status = (string) ($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = 'p.name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($status === 'active' || $status === 'draft') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE $whereSql
     ORDER BY p.id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

$sizeNamesByProduct = [];
try {
    foreach (db()->query(
        'SELECT ps.product_id, s.name FROM product_sizes ps
         INNER JOIN sizes s ON s.id = ps.size_id
         ORDER BY s.sort_order ASC, s.id ASC'
    ) as $row) {
        $sizeNamesByProduct[(int) $row['product_id']][] = $row['name'];
    }
} catch (Throwable $e) {
    $sizeNamesByProduct = [];
}

$pageTitle = 'المنتجات';
$pageSubtitle = 'إدارة قطع المتجر، الأسعار، المخزون والصور';
$activePage = 'products';

$pageParams = [];
foreach (['q', 'category', 'status'] as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $pageParams[$key] = $_GET[$key];
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-bar">
    <div class="admin-page-bar-info">
        <strong><?= $total ?></strong> منتج
    </div>
    <a class="admin-btn admin-btn-primary" href="<?= url('admin/product-form.php') ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        إضافة منتج
    </a>
</div>

<form class="admin-filters" method="get" action="<?= url('admin/products.php') ?>">
    <div class="admin-search">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="ابحث باسم المنتج…">
    </div>
    <select name="category" class="admin-select">
        <option value="0">كل التصنيفات</option>
        <?php foreach (all_categories() as $category): ?>
            <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status" class="admin-select">
        <option value="">كل الحالات</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>منشور</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>مسودة</option>
    </select>
    <button class="admin-btn admin-btn-outline" type="submit">تصفية</button>
    <?php if ($pageParams): ?>
        <a class="admin-btn admin-btn-ghost" href="<?= url('admin/products.php') ?>">مسح</a>
    <?php endif; ?>
</form>

<section class="admin-panel admin-panel-flush">
    <?php if ($products): ?>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table-products">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>التصنيف</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th>المقاسات</th>
                        <th>مميز</th>
                        <th>الحالة</th>
                        <th class="admin-th-actions">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div class="admin-product-cell">
                                    <img src="<?= product_image($product['image']) ?>" alt="<?= e($product['name']) ?>" width="54" height="66" loading="lazy">
                                    <div>
                                        <strong><?= e($product['name']) ?></strong>
                                        <small><?= e(excerpt($product['short_description'], 55)) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="admin-cell-sub"><?= e($product['category_name'] ?? 'بدون تصنيف') ?></span></td>
                            <td>
                                <strong><?= money($product['price']) ?></strong>
                                <?php if ((float) ($product['old_price'] ?? 0) > 0): ?>
                                    <span class="admin-old-price"><?= money($product['old_price']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="admin-stock-pill <?= (int) $product['stock'] <= 0 ? 'is-out' : ((int) $product['stock'] <= 3 ? 'is-low' : 'is-ok') ?>">
                                    <?= (int) $product['stock'] ?> قطعة
                                </span>
                            </td>
                            <td>
                                <?php $names = $sizeNamesByProduct[(int) $product['id']] ?? []; ?>
                                <?php if ($names): ?>
                                    <span class="admin-size-list"><?= e(implode(' · ', $names)) ?></span>
                                <?php else: ?>
                                    <span class="admin-cell-sub">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?= url('admin/products.php') ?>" class="admin-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <button class="admin-star <?= (int) $product['featured'] === 1 ? 'is-active' : '' ?>" type="submit" title="تمييز / إلغاء التمييز" aria-label="تمييز المنتج">★</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="<?= url('admin/products.php') ?>" class="admin-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <button class="admin-status-btn <?= $product['status'] === 'active' ? 'is-active' : '' ?>" type="submit" title="تغيير حالة النشر">
                                        <?= $product['status'] === 'active' ? 'منشور' : 'مسودة' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="admin-row-actions">
                                    <a class="admin-icon-btn" href="<?= url('product.php?id=' . (int) $product['id']) ?>" target="_blank" rel="noopener" title="عرض" aria-label="عرض المنتج">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a class="admin-icon-btn" href="<?= url('admin/product-form.php?id=' . (int) $product['id']) ?>" title="تعديل" aria-label="تعديل المنتج">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z"/></svg>
                                    </a>
                                    <form method="post" action="<?= url('admin/products.php') ?>" class="admin-inline-form" data-confirm="هل تريدين حذف هذا المنتج نهائياً؟">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button class="admin-icon-btn is-danger" type="submit" title="حذف" aria-label="حذف المنتج">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                                        </button>
                                    </form>
                                </div>
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
            <span class="admin-empty-icon">🛍️</span>
            <h3>لا توجد منتجات</h3>
            <p>ابدئي بإضافة أول قطعة إلى متجرك.</p>
            <a class="admin-btn admin-btn-primary" href="<?= url('admin/product-form.php') ?>">إضافة منتج جديد</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
