<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$search       = trim((string) ($_GET['q'] ?? ''));
$categoryId   = (int) ($_GET['category'] ?? 0);
$sort         = (string) ($_GET['sort'] ?? 'newest');
$featuredOnly = isset($_GET['featured']) && $_GET['featured'] === '1';
$minPrice     = isset($_GET['min']) && $_GET['min'] !== '' ? max(0, (float) $_GET['min']) : null;
$maxPrice     = isset($_GET['max']) && $_GET['max'] !== '' ? max(0, (float) $_GET['max']) : null;
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 9;

$where  = ["p.status = 'active'"];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
}
if ($featuredOnly) {
    $where[] = 'p.featured = 1';
}
if ($minPrice !== null) {
    $where[] = 'p.price >= ?';
    $params[] = $minPrice;
}
if ($maxPrice !== null && $maxPrice > 0) {
    $where[] = 'p.price <= ?';
    $params[] = $maxPrice;
}

$whereSql = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sortOptions = [
    'newest'     => 'الأحدث أولاً',
    'price-asc'  => 'السعر: من الأقل للأعلى',
    'price-desc' => 'السعر: من الأعلى للأقل',
    'oldest'     => 'الأقدم أولاً',
];
$orderBy = [
    'newest'     => 'p.created_at DESC, p.id DESC',
    'price-asc'  => 'p.price ASC, p.id DESC',
    'price-desc' => 'p.price DESC, p.id DESC',
    'oldest'     => 'p.id ASC',
][$sort] ?? 'p.created_at DESC, p.id DESC';

$sql = "SELECT p.*, c.name AS category_name
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE $whereSql
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$activeCategory = null;
foreach (all_categories() as $category) {
    if ((int) $category['id'] === $categoryId) {
        $activeCategory = $category;
        break;
    }
}

$pageParams = [];
foreach (['q', 'category', 'sort', 'featured', 'min', 'max'] as $key) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        $pageParams[$key] = $_GET[$key];
    }
}

$preserve = $pageParams;
unset($preserve['category'], $preserve['page']);
$categoryUrl = static function (int $id) use ($preserve): string {
    $query = $preserve;
    if ($id > 0) {
        $query['category'] = $id;
    }
    return url('shop.php' . ($query ? '?' . http_build_query($query) : ''));
};

$toggleParams = $pageParams;
if ($featuredOnly) {
    unset($toggleParams['featured']);
} else {
    $toggleParams['featured'] = '1';
}
unset($toggleParams['page']);
$featuredUrl = url('shop.php' . ($toggleParams ? '?' . http_build_query($toggleParams) : ''));

$pageTitle = $activeCategory ? $activeCategory['name'] : 'المتجر';
if ($search !== '') {
    $pageTitle = 'البحث: ' . $search;
}
$metaDescription = $activeCategory['description'] ?? setting('about_text', '');
require __DIR__ . '/includes/header.php';
?>

<div class="page-hero page-hero-shop">
    <div class="container">
        <nav class="breadcrumb" aria-label="مسار التنقل">
            <a href="<?= url('index.php') ?>">الرئيسية</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('shop.php') ?>">المتجر</a>
            <?php if ($activeCategory): ?>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?= e($activeCategory['name']) ?></span>
            <?php endif; ?>
        </nav>
        <h1><?= e($activeCategory['name'] ?? ($search !== '' ? 'نتائج البحث' : 'كل المنتجات')) ?></h1>
        <p>
            <?php if ($search !== ''): ?>
                نتائج البحث عن «<?= e($search) ?>» — <?= $total ?> قطعة
            <?php elseif ($activeCategory): ?>
                <?= e($activeCategory['description'] ?: 'قطع مختارة بعناية من مراكشية') ?>
            <?php else: ?>
                اكتشفي تشكيلتنا الكاملة من القفاطين والفساتين والجلابيات والإكسسوارات.
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="container shop-layout">
    <aside class="shop-sidebar">
        <div class="sidebar-card">
            <h2 class="sidebar-title">التصنيفات</h2>
            <ul class="category-filter">
                <li>
                    <a class="<?= $categoryId === 0 ? 'is-active' : '' ?>" href="<?= $categoryUrl(0) ?>">
                        <span>كل المنتجات</span>
                    </a>
                </li>
                <?php foreach (all_categories() as $category): ?>
                    <li>
                        <a class="<?= $categoryId === (int) $category['id'] ? 'is-active' : '' ?>" href="<?= $categoryUrl((int) $category['id']) ?>">
                            <span><?= e($category['name']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="sidebar-card">
            <h2 class="sidebar-title">السعر</h2>
            <form class="price-filter" method="get" action="<?= url('shop.php') ?>">
                <?php foreach (['q', 'category', 'sort', 'featured'] as $key): ?>
                    <?php if (isset($pageParams[$key])): ?>
                        <input type="hidden" name="<?= e($key) ?>" value="<?= e($pageParams[$key]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="price-inputs">
                    <label>من
                        <input type="number" name="min" min="0" step="1" value="<?= $minPrice !== null ? e((string) $minPrice) : '' ?>" placeholder="0">
                    </label>
                    <label>إلى
                        <input type="number" name="max" min="0" step="1" value="<?= $maxPrice !== null ? e((string) $maxPrice) : '' ?>" placeholder="2000">
                    </label>
                </div>
                <button class="btn btn-outline btn-block" type="submit">تطبيق</button>
            </form>
        </div>

        <div class="sidebar-promo">
            <span class="sidebar-promo-kicker">توصيل مجاني</span>
            <strong>للطلبات فوق <?= money(setting('free_shipping_threshold', '500')) ?></strong>
            <p>وكل طلب يوصلك مغلّف كهدية.</p>
        </div>
    </aside>

    <section class="shop-content">
        <div class="shop-toolbar">
            <p class="results-count"><strong><?= $total ?></strong> قطعة متوفرة</p>
            <div class="toolbar-actions">
                <a class="chip<?= $featuredOnly ? ' is-active' : '' ?>" href="<?= $featuredUrl ?>">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 3 2.2 5.5L20 10l-4.4 3.7L17 20l-5-3-5 3 1.4-6.3L4 10l5.8-1.5L12 3Z"/></svg>
                    مختاراتنا
                </a>
                <form class="sort-form" method="get" action="<?= url('shop.php') ?>">
                    <?php foreach (['q', 'category', 'featured', 'min', 'max'] as $key): ?>
                        <?php if (isset($pageParams[$key])): ?>
                            <input type="hidden" name="<?= e($key) ?>" value="<?= e($pageParams[$key]) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label class="sr-only" for="sortSelect">ترتيب المنتجات</label>
                    <select name="sort" id="sortSelect" class="form-select" data-autosubmit>
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-outline" type="submit">ترتيب</button></noscript>
                </form>
            </div>
        </div>

        <?php if ($products): ?>
            <div class="product-grid product-grid-shop">
                <?php foreach ($products as $product): ?>
                    <?php include __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>

            <?= pagination_links($page, $pages, $pageParams) ?>
        <?php else: ?>
            <div class="empty-state empty-state-large">
                <span class="empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </span>
                <h2>لا توجد منتجات مطابقة</h2>
                <p>جرّبي تعديل البحث أو التصنيف أو نطاق السعر.</p>
                <a class="btn btn-primary" href="<?= url('shop.php') ?>">عرض كل المنتجات</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
