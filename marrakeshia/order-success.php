<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$orderId = (int) ($_SESSION['last_order_id'] ?? 0);
if ($orderId < 1) {
    redirect(url('index.php'));
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    unset($_SESSION['last_order_id']);
    redirect(url('index.php'));
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'تم استلام طلبك';
require __DIR__ . '/includes/header.php';
?>

<div class="container section success-page">
    <div class="success-hero">
        <span class="success-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7 9 18l-5-5"/></svg>
        </span>
        <span class="section-kicker">شكراً لثقتك بمراكشية</span>
        <h1>تم استلام طلبك بنجاح!</h1>
        <p>سنتواصل معك على الرقم <strong dir="ltr"><?= e($order['phone']) ?></strong> لتأكيد موعد التوصيل. راجعي تفاصيل طلبك أدناه.</p>
        <div class="success-meta">
            <div><span>رقم الطلب</span><strong dir="ltr"><?= e($order['order_number']) ?></strong></div>
            <div><span>تاريخ الطلب</span><strong><?= ar_date($order['created_at']) ?></strong></div>
            <div><span>حالة الطلب</span><strong><?= status_badge($order['status']) ?></strong></div>
        </div>
    </div>

    <div class="success-layout">
        <div class="success-details">
            <div class="panel">
                <h2>قطع الطلب</h2>
                <ul class="order-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <span class="order-item-name"><?= e($item['product_name']) ?><?php if (!empty($item['size'])): ?> <small>(المقاس <?= e($item['size']) ?>)</small><?php endif; ?></span>
                            <span class="order-item-qty">× <?= (int) $item['quantity'] ?></span>
                            <span class="order-item-price"><?= money((float) $item['price'] * (int) $item['quantity']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="summary-lines">
                    <div><dt>المجموع الفرعي</dt><dd><?= money($order['subtotal']) ?></dd></div>
                    <div><dt>التوصيل</dt><dd><?= (float) $order['shipping'] > 0 ? money($order['shipping']) : '<span class="free-tag">مجاني</span>' ?></dd></div>
                    <div class="summary-total"><dt>الإجمالي</dt><dd><?= money($order['total']) ?></dd></div>
                </dl>
            </div>

            <div class="panel">
                <h2>معلومات التوصيل</h2>
                <ul class="info-list">
                    <li><span>الاسم</span><strong><?= e($order['customer_name']) ?></strong></li>
                    <li><span>الهاتف</span><strong dir="ltr"><?= e($order['phone']) ?></strong></li>
                    <li><span>المدينة</span><strong><?= e($order['city']) ?></strong></li>
                    <li><span>العنوان</span><strong><?= e($order['address']) ?></strong></li>
                    <?php if ($order['notes']): ?>
                        <li><span>ملاحظات</span><strong><?= e($order['notes']) ?></strong></li>
                    <?php endif; ?>
                    <li><span>طريقة الدفع</span><strong><?= payment_label($order['payment_method']) ?></strong></li>
                </ul>
            </div>
        </div>

        <aside class="success-side">
            <div class="panel panel-soft">
                <h2>ماذا بعد؟</h2>
                <ol class="timeline">
                    <li class="is-done"><span></span><div><strong>تم استلام الطلب</strong><small>سجّلنا طلبك بنجاح</small></div></li>
                    <li><span></span><div><strong>تأكيد هاتفي</strong><small>سنتصل بك خلال ساعات العمل</small></div></li>
                    <li><span></span><div><strong>التغليف والشحن</strong><small>تجهيز طلبك بعناية</small></div></li>
                    <li><span></span><div><strong>التوصيل والدفع</strong><small><?= $order['payment_method'] === 'cod' ? 'تستلمين وتدفعين عند الاستلام' : 'إتمام الدفع عبر ' . payment_label($order['payment_method']) ?></small></div></li>
                </ol>
                <a class="btn btn-primary btn-block" href="<?= url('shop.php') ?>">متابعة التسوق</a>
                <?php if (is_logged_in()): ?>
                    <a class="btn btn-ghost btn-block" href="<?= url('account.php') ?>">طلباتي</a>
                <?php endif; ?>
                <a class="btn btn-ghost btn-block" href="https://wa.me/<?= e(setting('whatsapp', '212600000000')) ?>?text=<?= rawurlencode('مرحباً، بخصوص طلبي ' . $order['order_number']) ?>" target="_blank" rel="noopener">تواصلي معنا عبر واتساب</a>
            </div>
            <p class="success-note">احتفظي برقم الطلب للمتابعة. أي استفسار؟ نحن هنا من أجلك.</p>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
