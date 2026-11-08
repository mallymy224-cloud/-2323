<?php
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    redirect(url('login.php'));
}

$user = current_user();
$orderId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$orderId, (int) $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'الطلب غير موجود';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="container section">
        <div class="empty-state empty-state-large">
            <span class="empty-icon" aria-hidden="true">✦</span>
            <h1>لم نجد هذا الطلب</h1>
            <p>قد يكون رقم الطلب غير صحيح أو لا ينتمي إلى حسابك.</p>
            <a class="btn btn-primary" href="<?= url('account.php') ?>">العودة إلى حسابي</a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$steps = ['pending' => 'تم الطلب', 'confirmed' => 'تم التأكيد', 'shipped' => 'تم الشحن', 'delivered' => 'تم التسليم'];
$stepKeys = array_keys($steps);
$currentIndex = array_search($order['status'], $stepKeys, true);
$isCancelled = $order['status'] === 'cancelled';

$pageTitle = 'تفاصيل الطلب ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>

<div class="container section account-order-page">
    <nav class="breadcrumb breadcrumb-page" aria-label="مسار التنقل">
        <a href="<?= url('index.php') ?>">الرئيسية</a>
        <span aria-hidden="true">/</span>
        <a href="<?= url('account.php') ?>">حسابي</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($order['order_number']) ?></span>
    </nav>

    <div class="panel">
        <div class="panel-head">
            <div>
                <span class="section-kicker">تفاصيل الطلب</span>
                <h1 class="panel-title" dir="ltr"><?= e($order['order_number']) ?></h1>
                <p class="panel-sub">تم الطلب في <?= ar_datetime($order['created_at']) ?></p>
            </div>
            <div class="panel-head-actions">
                <?= status_badge($order['status']) ?>
                <a class="btn btn-outline btn-sm" href="<?= url('account.php') ?>">كل الطلبات</a>
            </div>
        </div>

        <?php if ($isCancelled): ?>
            <div class="alert alert-danger"><span>تم إلغاء هذا الطلب. إذا كان لديك أي استفسار تواصلي معنا.</span></div>
        <?php else: ?>
            <ol class="order-timeline">
                <?php foreach ($steps as $key => $label): ?>
                    <?php $index = array_search($key, $stepKeys, true); ?>
                    <li class="<?= $index <= $currentIndex ? 'is-done' : '' ?> <?= $index === $currentIndex ? 'is-current' : '' ?>">
                        <span class="timeline-dot"><?= $index < $currentIndex ? '✓' : $index + 1 ?></span>
                        <strong><?= e($label) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
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
                    <?php if ($order['notes']): ?><li><span>ملاحظات</span><strong><?= e($order['notes']) ?></strong></li><?php endif; ?>
                    <li><span>طريقة الدفع</span><strong><?= payment_label($order['payment_method']) ?></strong></li>
                </ul>
            </div>
        </div>

        <aside class="success-side">
            <div class="panel panel-soft">
                <h2>هل تحتاجين مساعدة؟</h2>
                <p class="muted">فريق مراكشية رهن إشارتك للإجابة عن أي سؤال بخصوص طلبك.</p>
                <a class="btn btn-primary btn-block" href="https://wa.me/<?= e(setting('whatsapp', '212600000000')) ?>?text=<?= rawurlencode('مرحباً، بخصوص طلبي ' . $order['order_number']) ?>" target="_blank" rel="noopener">تواصلي عبر واتساب</a>
                <a class="btn btn-ghost btn-block" href="tel:<?= e(preg_replace('/\s+/', '', setting('phone', '+212600000000'))) ?>">اتصلي بنا</a>
            </div>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
