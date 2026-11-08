<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$featured = $pdo->query(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active' AND p.featured = 1
     ORDER BY p.created_at DESC, p.id DESC LIMIT 8"
)->fetchAll();

$newArrivals = $pdo->query(
    "SELECT p.*, c.name AS category_name
     FROM products p LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.status = 'active'
     ORDER BY p.created_at DESC, p.id DESC LIMIT 4"
)->fetchAll();

$categories = $pdo->query(
    "SELECT c.*, COUNT(p.id) AS products_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
     GROUP BY c.id
     ORDER BY c.sort_order ASC, c.id ASC"
)->fetchAll();

$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$promoProduct = $pdo->query(
    "SELECT * FROM products WHERE status = 'active' ORDER BY old_price DESC LIMIT 1"
)->fetch() ?: null;

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-pattern" aria-hidden="true"></div>
    <div class="container hero-grid">
        <div class="hero-content">
            <span class="eyebrow">
                <span class="eyebrow-dot" aria-hidden="true"></span>
                تشكيلة <?= date('Y') ?> — حياك الله في مراكشية
            </span>
            <h1>أناقة مغربية أصيلة<br><span class="text-accent">تروي حكاية</span></h1>
            <p class="hero-text">
                قطع مختارة بعناية من قلوب مراكش: قفاطين، عبايات وفساتين بتطريز يدوي وأقمشة طبيعية،
                لتكوني أنتِ نجمة كل مناسبة.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= url('shop.php') ?>">
                    تسوّقي الآن
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                </a>
                <a class="btn btn-ghost btn-lg" href="<?= url('shop.php?category=1') ?>">اكتشفي القفاطين</a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><strong><?= $totalProducts ?>+</strong><span>قطعة مختارة</span></div>
                <span class="hero-stat-sep" aria-hidden="true"></span>
                <div class="hero-stat"><strong>+500</strong><span>عميلة سعيدة</span></div>
                <span class="hero-stat-sep" aria-hidden="true"></span>
                <div class="hero-stat"><strong>48 س</strong><span>توصيل سريع</span></div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-frame">
                <img src="<?= e(hero_image()) ?>" alt="تشكيلة قفاطين مراكشية بتطريز يدوي" width="720" height="880">
            </div>
            <div class="hero-chip hero-chip-top">
                <span class="chip-icon">✦</span>
                <div><strong>تطريز يدوي</strong><small>من قلب مراكش</small></div>
            </div>
            <div class="hero-chip hero-chip-bottom">
                <span class="chip-icon">❁</span>
                <div><strong>أقمشة طبيعية</strong><small>مريحة وتدوم</small></div>
            </div>
            <span class="hero-orbit" aria-hidden="true"></span>
        </div>
    </div>
</section>

<section class="feature-strip" aria-label="مزايا المتجر">
    <div class="container feature-grid">
        <div class="feature-item">
            <span class="feature-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>
            </span>
            <div><strong>توصيل لكل المغرب</strong><span>خلال 24 إلى 48 ساعة</span></div>
        </div>
        <div class="feature-item">
            <span class="feature-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h3"/></svg>
            </span>
            <div><strong>الدفع عند الاستلام</strong><span>ادفعي بعد أن تستلمي</span></div>
        </div>
        <div class="feature-item">
            <span class="feature-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m12 3 2.2 5.5L20 10l-4.4 3.7L17 20l-5-3-5 3 1.4-6.3L4 10l5.8-1.5L12 3Z"/></svg>
            </span>
            <div><strong>جودة مضمونة</strong><span>أقمشة وتطريز أصيل</span></div>
        </div>
        <div class="feature-item">
            <span class="feature-icon">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="8" width="18" height="13" rx="1"/><path d="M3 8h18v5H3zM12 8v13"/><path d="M12 8C9 3 4 4 5 7c.5 1.5 4 1 7 1Zm0 0c3-5 8-4 7-1-.5 1.5-4 1-7 1Z"/></svg>
            </span>
            <div><strong>تغليف هدية</strong><span>مجاناً مع كل طلب</span></div>
        </div>
    </div>
</section>

<section class="section categories-section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">تصفحي بسهولة</span>
                <h2 class="section-title">تسوّقي حسب التصنيف</h2>
            </div>
            <a class="link-more" href="<?= url('shop.php') ?>">كل التصنيفات
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            </a>
        </div>

        <div class="category-grid">
            <?php foreach ($categories as $index => $category): ?>
                <a class="category-card" href="<?= url('shop.php?category=' . (int) $category['id']) ?>">
                    <span class="category-arch">
                        <img src="<?= product_image($category['image']) ?>" alt="<?= e($category['name']) ?>" loading="lazy" width="480" height="600">
                    </span>
                    <span class="category-info">
                        <strong><?= e($category['name']) ?></strong>
                        <small><?= (int) $category['products_count'] ?> قطعة</small>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section products-section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">مختاراتنا لك</span>
                <h2 class="section-title">قطع يعشقها الجميع</h2>
            </div>
            <a class="link-more" href="<?= url('shop.php?featured=1') ?>">عرض الكل
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            </a>
        </div>

        <?php if ($featured): ?>
            <div class="product-grid">
                <?php foreach ($featured as $product): ?>
                    <?php include __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>لا توجد منتجات مميزة بعد. أضيفي منتجات من لوحة التحكم.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="promo-section">
    <div class="container">
        <div class="promo-banner">
            <div class="promo-pattern" aria-hidden="true"></div>
            <div class="promo-content">
                <span class="promo-badge">عرض محدود</span>
                <h2>خصم حتى 30٪<br>على مجموعة القفاطين</h2>
                <p>قطع فاخرة بتطريز يدوي، بأسعار تليق بكل مناسبة. الكمية محدودة.</p>
                <a class="btn btn-light btn-lg" href="<?= url('shop.php?category=1') ?>">
                    اقتني قطعتك
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                </a>
            </div>
            <div class="promo-visual">
                <span class="promo-arch">
                    <img src="<?= product_image($promoProduct['image'] ?? 'p10.svg') ?>" alt="قفطان سهرة ذهبي" loading="lazy" width="520" height="650">
                </span>
                <span class="promo-stamp">-30٪</span>
            </div>
        </div>
    </div>
</section>

<section class="section new-section">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="section-kicker">حديث الوصول</span>
                <h2 class="section-title">وصل حديثاً إلى مراكشية</h2>
            </div>
            <a class="link-more" href="<?= url('shop.php?sort=newest') ?>">كل الجديد
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            </a>
        </div>
        <div class="product-grid product-grid-4">
            <?php foreach ($newArrivals as $product): ?>
                <?php include __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section story-section">
    <div class="container story-grid">
        <div class="story-visual">
            <span class="story-arch story-arch-main">
                <img src="<?= url('assets/images/products/p5.svg') ?>" alt="جلابية أطلس بتطريز أمازيغي" loading="lazy" width="480" height="600">
            </span>
            <span class="story-arch story-arch-small">
                <img src="<?= url('assets/images/products/p8.svg') ?>" alt="وشاح حرير أمازيغي" loading="lazy" width="320" height="400">
            </span>
            <span class="story-seal" aria-hidden="true">مراكش<br><small>منذ 2020</small></span>
        </div>
        <div class="story-content">
            <span class="section-kicker">حكايتنا</span>
            <h2 class="section-title">من قلب مراكش… إلى خزانتك</h2>
            <p><?= e(setting('about_text', 'مراكشية متجر مغربي يحتفي بالأناقة الأصيلة.')) ?></p>
            <ul class="story-list">
                <li><span>✦</span> تصاميم تجمع بين التراث المغربي واللمسة العصرية.</li>
                <li><span>✦</span> أقمشة طبيعية مختارة بعناية، وتطريز يدوي من حرفيين محليين.</li>
                <li><span>✦</span> كل قطعة تُغلّف بعناية وكأنها هدية.</li>
            </ul>
            <a class="btn btn-outline" href="<?= url('shop.php') ?>">اكتشفي المجموعة</a>
        </div>
    </div>
</section>

<section class="section testimonials-section">
    <div class="container">
        <div class="section-head section-head-center">
            <div>
                <span class="section-kicker">آراء عميلاتنا</span>
                <h2 class="section-title">قالوا عن مراكشية</h2>
            </div>
        </div>
        <div class="testimonial-grid">
            <figure class="testimonial-card">
                <div class="stars" aria-label="5 من 5">★★★★★</div>
                <blockquote>«القفطان وصلني أجمل من الصور، والتطريز دقيق جداً. التغليف كان هدية بحد ذاتها. شكراً مراكشية!»</blockquote>
                <figcaption><span class="avatar">س</span><div><strong>سلمى العمراني</strong><small>مراكش</small></div></figcaption>
            </figure>
            <figure class="testimonial-card">
                <div class="stars" aria-label="5 من 5">★★★★★</div>
                <blockquote>«طلبت جلابية أطلس وكانت مريحة جداً وخامتها ممتازة. التوصيل كان سريعاً والتواصل راقٍ.»</blockquote>
                <figcaption><span class="avatar">ل</span><div><strong>ليلى الفاسي</strong><small>فاس</small></div></figcaption>
            </figure>
            <figure class="testimonial-card">
                <div class="stars" aria-label="5 من 5">★★★★★</div>
                <blockquote>«أخيراً وجدت متجراً مغربياً يهتم بالتفاصيل. الفستان أنيق والقياس مضبوط تماماً كما في الجدول.»</blockquote>
                <figcaption><span class="avatar">خ</span><div><strong>خديجة بناني</strong><small>الدار البيضاء</small></div></figcaption>
            </figure>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
