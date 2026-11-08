<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$productId = (int) ($_GET['id'] ?? 0);
$product = find_product($productId);

if (!$product) {
    http_response_code(404);
    $pageTitle = 'المنتج غير موجود';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container section">
        <div class="empty-state empty-state-large">
            <span class="empty-icon" aria-hidden="true">✦</span>
            <h1>لم نجد هذه القطعة</h1>
            <p>ربما تم حذف المنتج أو تغيّر رابطه. اكتشفي باقي التشكيلة.</p>
            <a class="btn btn-primary" href="<?= url('shop.php') ?>">العودة إلى المتجر</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$relatedStmt = $pdo->prepare(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' AND p.id <> ? AND p.category_id <=> ?
     ORDER BY p.created_at DESC LIMIT 4"
);
$relatedStmt->execute([(int) $product['id'], $product['category_id'] !== null ? (int) $product['category_id'] : null]);
$related = $relatedStmt->fetchAll();

$productStock = (int) $product['stock'];
$discount = discount_percent($product);
$productSizes = product_sizes((int) $product['id']);
$productImages = product_all_images($product);
$pageTitle = $product['name'];
$metaDescription = $product['short_description'] ?: excerpt($product['description'], 160);
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <nav class="breadcrumb breadcrumb-page" aria-label="مسار التنقل">
        <a href="<?= url('index.php') ?>">الرئيسية</a>
        <span aria-hidden="true">/</span>
        <a href="<?= url('shop.php') ?>">المتجر</a>
        <?php if (!empty($product['category_name'])): ?>
            <span aria-hidden="true">/</span>
            <a href="<?= url('shop.php?category=' . (int) $product['category_id']) ?>"><?= e($product['category_name']) ?></a>
        <?php endif; ?>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($product['name']) ?></span>
    </nav>

    <div class="product-detail">
        <div class="product-gallery">
            <div class="product-main-media">
                <img id="mainProductImage" src="<?= e($productImages[0]) ?>" alt="<?= e($product['name']) ?>" width="700" height="900">
                <?php if ($discount > 0): ?>
                    <span class="product-badge badge-sale">-<?= $discount ?>%</span>
                <?php endif; ?>
                <span class="media-frame" aria-hidden="true"></span>
                <?php if (count($productImages) > 1): ?>
                    <span class="gallery-count" aria-hidden="true"><?= count($productImages) ?> صور</span>
                <?php endif; ?>
            </div>

            <?php if (count($productImages) > 1): ?>
                <div class="product-thumbs" role="group" aria-label="صور المنتج">
                    <?php foreach ($productImages as $index => $src): ?>
                        <button class="product-thumb<?= $index === 0 ? ' is-active' : '' ?>" type="button"
                                data-gallery-src="<?= e($src) ?>" aria-label="عرض الصورة <?= $index + 1 ?>"
                                aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                            <img src="<?= e($src) ?>" alt="" loading="lazy" width="120" height="150">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="product-media-notes">
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 3 2.2 5.5L20 10l-4.4 3.7L17 20l-5-3-5 3 1.4-6.3L4 10l5.8-1.5L12 3Z"/></svg> تطريز يدوي</span>
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 7 9 18l-5-5"/></svg> أقمشة مختارة</span>
                <span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="1"/><path d="M3 8h18v5H3zM12 8v13"/></svg> تغليف هدية</span>
            </div>
        </div>

        <div class="product-info">
            <?php if (!empty($product['category_name'])): ?>
                <a class="product-cat product-cat-lg" href="<?= url('shop.php?category=' . (int) $product['category_id']) ?>"><?= e($product['category_name']) ?></a>
            <?php endif; ?>
            <h1 class="product-title"><?= e($product['name']) ?></h1>

            <div class="product-rating">
                <span class="stars" aria-label="تقييم 4.9 من 5">★★★★★</span>
                <span class="rating-text">4.9 — +120 تقييم</span>
            </div>

            <div class="product-price product-price-lg">
                <span class="price-now"><?= money($product['price']) ?></span>
                <?php if (!empty($product['old_price']) && (float) $product['old_price'] > (float) $product['price']): ?>
                    <span class="price-old"><?= money($product['old_price']) ?></span>
                    <span class="price-save">توفير <?= money((float) $product['old_price'] - (float) $product['price']) ?></span>
                <?php endif; ?>
            </div>

            <p class="product-short"><?= e($product['short_description']) ?></p>

            <div class="stock-line <?= $productStock <= 0 ? 'is-out' : ($productStock <= 3 ? 'is-low' : 'is-in') ?>">
                <span class="stock-dot" aria-hidden="true"></span>
                <?php if ($productStock <= 0): ?>
                    نفد مخزون هذه القطعة حالياً
                <?php elseif ($productStock <= 3): ?>
                    متوفرة — بقي <?= $productStock ?> قطع فقط
                <?php else: ?>
                    متوفرة في المخزون — جاهزة للشحن
                <?php endif; ?>
            </div>

            <?php if ($productSizes): ?>
                <div class="size-picker" data-size-picker>
                    <div class="size-picker-head">
                        <strong>المقاسات المتوفرة</strong>
                        <span>اختاري المقاس المناسب</span>
                    </div>
                    <div class="size-options">
                        <?php foreach ($productSizes as $index => $size): ?>
                            <button class="size-chip<?= $index === 0 ? ' is-active' : '' ?>" type="button" data-size-id="<?= (int) $size['id'] ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"><?= e($size['name']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form class="js-add-to-cart product-buy" method="post" action="<?= url('cart-action.php') ?>" data-product-name="<?= e($product['name']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="size_id" value="<?= $productSizes ? (int) $productSizes[0]['id'] : 0 ?>" data-size-input>

                <div class="qty-control" data-qty>
                    <button type="button" class="qty-btn" data-qty-minus aria-label="إنقاص الكمية">−</button>
                    <input type="number" name="quantity" value="1" min="1" max="<?= max(1, $productStock) ?>" inputmode="numeric" aria-label="الكمية">
                    <button type="button" class="qty-btn" data-qty-plus aria-label="زيادة الكمية">+</button>
                </div>

                <button class="btn btn-primary btn-lg product-buy-btn" type="submit" <?= $productStock < 1 ? 'disabled' : '' ?>>
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 8h14l-1.2 11H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    <?= $productStock < 1 ? 'نفد المخزون' : 'أضف إلى السلة' ?>
                </button>
            </form>

            <div class="trust-row">
                <div><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg><span>توصيل 24-48 ساعة</span></div>
                <div><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/></svg><span>الدفع عند الاستلام</span></div>
                <div><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 7 9 18l-5-5"/></svg><span>جودة مضمونة</span></div>
            </div>

            <div class="product-accordion">
                <?php if ($product['description']): ?>
                    <details class="accordion-item" open>
                        <summary>وصف القطعة</summary>
                        <div class="accordion-body"><p><?= nl2br(e($product['description'])) ?></p></div>
                    </details>
                <?php endif; ?>
                <?php if ($product['details']): ?>
                    <details class="accordion-item">
                        <summary>التفاصيل والمقاسات</summary>
                        <div class="accordion-body"><p><?= nl2br(e($product['details'])) ?></p></div>
                    </details>
                <?php endif; ?>
                <details class="accordion-item">
                    <summary>التوصيل والإرجاع</summary>
                    <div class="accordion-body">
                        <p>التوصيل متاح لكل مدن المغرب خلال 24 إلى 48 ساعة عمل. الدفع عند الاستلام.</p>
                        <p>الاستبدال أو الإرجاع ممكن خلال 48 ساعة من الاستلام، بشرط أن تكون القطعة في حالتها الأصلية وبتغليفها.</p>
                        <p>للاستفسار: <a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone', '+212600000000'))) ?>" dir="ltr"><?= e(setting('phone', '+212 6 12 34 56 78')) ?></a></p>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>

<?php if ($related): ?>
    <section class="section related-section">
        <div class="container">
            <div class="section-head">
                <div>
                    <span class="section-kicker">قد يعجبك أيضاً</span>
                    <h2 class="section-title">قطع من نفس العائلة</h2>
                </div>
                <a class="link-more" href="<?= url('shop.php?category=' . (int) $product['category_id']) ?>">عرض التصنيف
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                </a>
            </div>
            <div class="product-grid product-grid-4">
                <?php foreach ($related as $product): ?>
                    <?php include __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
