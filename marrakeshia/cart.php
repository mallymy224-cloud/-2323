<?php
require_once __DIR__ . '/includes/functions.php';

if (is_post()) {
    verify_csrf();

    if (isset($_POST['remove'])) {
        $key = (string) $_POST['remove'];
        if (ctype_digit($key)) {
            $key = cart_key((int) $key, 0);
        }
        cart_remove($key);
        flash('success', 'تم حذف القطعة من السلة.');
    } elseif (($_POST['action'] ?? '') === 'clear') {
        cart_clear();
        flash('success', 'تم تفريغ السلة.');
    } elseif (($_POST['action'] ?? '') === 'update') {
        $quantities = $_POST['quantity'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $key => $qty) {
                cart_set((string) $key, (int) $qty);
            }
        }
        flash('success', 'تم تحديث كميات السلة.');
    }

    redirect(url('cart.php'));
}

$summary = cart_summary();
$pageTitle = 'سلة التسوق';
require __DIR__ . '/includes/header.php';

$remainingForFree = max(0, $summary['free_threshold'] - $summary['subtotal']);
$progress = $summary['free_threshold'] > 0
    ? min(100, (int) round(($summary['subtotal'] / $summary['free_threshold']) * 100))
    : 100;
?>

<div class="page-hero page-hero-slim">
    <div class="container">
        <nav class="breadcrumb" aria-label="مسار التنقل">
            <a href="<?= url('index.php') ?>">الرئيسية</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">سلة التسوق</span>
        </nav>
        <h1>سلة التسوق</h1>
        <p><?= $summary['count'] > 0 ? 'لديك ' . $summary['count'] . ' قطعة في السلة — خطوة واحدة تفصلك عن إطلالتك الجديدة.' : 'سلتك فارغة حالياً.' ?></p>
    </div>
</div>

<div class="container section section-cart">
    <?php if (!$summary['items']): ?>
        <div class="empty-state empty-state-large">
            <span class="empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 8h14l-1.2 11H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
            </span>
            <h2>سلتك فارغة</h2>
            <p>لم تضيفي أي قطعة بعد. اكتشفي تشكيلتنا واختاري ما يناسب إطلالتك.</p>
            <a class="btn btn-primary btn-lg" href="<?= url('shop.php') ?>">ابدئي التسوق</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-main">
                <div class="cart-head-row">
                    <h2>القطع المختارة</h2>
                    <form method="post" action="<?= url('cart.php') ?>" class="cart-clear-form" data-confirm="هل تريدين تفريغ السلة بالكامل؟">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                        <button class="link-danger" type="submit">تفريغ السلة</button>
                    </form>
                </div>

                <form method="post" action="<?= url('cart.php') ?>" class="cart-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">

                    <div class="cart-table-wrap">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th scope="col">المنتج</th>
                                    <th scope="col">السعر</th>
                                    <th scope="col">الكمية</th>
                                    <th scope="col">المجموع</th>
                                    <th scope="col"><span class="sr-only">حذف</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['items'] as $item): ?>
                                    <tr>
                                        <td data-label="المنتج">
                                            <div class="cart-product">
                                                <a class="cart-thumb" href="<?= url('product.php?id=' . (int) $item['id']) ?>">
                                                    <img src="<?= product_image($item['image']) ?>" alt="<?= e($item['name']) ?>" width="90" height="112" loading="lazy">
                                                </a>
                                                <div>
                                                    <a class="cart-name" href="<?= url('product.php?id=' . (int) $item['id']) ?>"><?= e($item['name']) ?></a>
                                                    <span class="cart-cat"><?= e($item['category_name'] ?? '') ?></span>
                                                    <?php if (!empty($item['size_name'])): ?>
                                                        <span class="cart-size">المقاس: <?= e($item['size_name']) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ((int) $item['stock'] <= 3): ?>
                                                        <span class="cart-stock-note">بقي <?= (int) $item['stock'] ?> فقط في المخزون</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="السعر"><span class="cart-price"><?= money($item['price']) ?></span></td>
                                        <td data-label="الكمية">
                                            <div class="qty-control qty-control-sm" data-qty>
                                                <button type="button" class="qty-btn" data-qty-minus aria-label="إنقاص الكمية">−</button>
                                                <input type="number" name="quantity[<?= e($item['cart_key']) ?>]" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= max(1, (int) $item['stock']) ?>" inputmode="numeric" aria-label="كمية <?= e($item['name']) ?>">
                                                <button type="button" class="qty-btn" data-qty-plus aria-label="زيادة الكمية">+</button>
                                            </div>
                                        </td>
                                        <td data-label="المجموع"><strong class="cart-line-total"><?= money($item['line_total']) ?></strong></td>
                                        <td data-label="حذف">
                                            <button class="remove-btn" type="submit" name="remove" value="<?= e($item['cart_key']) ?>" formnovalidate aria-label="حذف <?= e($item['name']) ?>">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v5M14 11v5"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="cart-actions">
                        <a class="btn btn-ghost" href="<?= url('shop.php') ?>">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            متابعة التسوق
                        </a>
                        <button class="btn btn-outline" type="submit">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg>
                            تحديث السلة
                        </button>
                    </div>
                </form>
            </div>

            <aside class="cart-summary">
                <div class="summary-card">
                    <h2>ملخص الطلب</h2>

                    <div class="free-shipping-box <?= $summary['shipping'] === 0.0 ? 'is-free' : '' ?>">
                        <?php if ($summary['shipping'] === 0.0): ?>
                            <strong>🎉 حصلتِ على توصيل مجاني!</strong>
                            <p>طلبك مؤهل للتوصيل المجاني.</p>
                        <?php else: ?>
                            <strong>أضيفي <?= money($remainingForFree) ?> للتوصيل المجاني</strong>
                            <div class="progress-track"><span style="width: <?= $progress ?>%"></span></div>
                        <?php endif; ?>
                    </div>

                    <dl class="summary-lines">
                        <div><dt>المجموع الفرعي</dt><dd><?= money($summary['subtotal']) ?></dd></div>
                        <div><dt>التوصيل</dt><dd><?= $summary['shipping'] > 0 ? money($summary['shipping']) : '<span class="free-tag">مجاني</span>' ?></dd></div>
                        <div class="summary-total"><dt>الإجمالي</dt><dd><?= money($summary['total']) ?></dd></div>
                    </dl>

                    <a class="btn btn-primary btn-lg btn-block" href="<?= url('checkout.php') ?>">
                        إتمام الطلب
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    </a>

                    <ul class="summary-trust">
                        <li><span>✓</span> الدفع عند الاستلام</li>
                        <li><span>✓</span> توصيل لكل المغرب خلال 24-48 ساعة</li>
                        <li><span>✓</span> تغليف هدية مجاني</li>
                    </ul>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
