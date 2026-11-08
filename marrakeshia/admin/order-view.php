<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'لم يتم العثور على الطلب المطلوب.');
    redirect(url('admin/orders.php'));
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

if (is_post()) {
    verify_csrf();
    $newStatus = (string) ($_POST['status'] ?? '');

    if (!isset(order_statuses()[$newStatus])) {
        flash('error', 'حالة الطلب غير صالحة.');
        redirect(url('admin/order-view.php?id=' . $id));
    }

    if ($newStatus === $order['status']) {
        flash('info', 'لم تتغير حالة الطلب.');
        redirect(url('admin/order-view.php?id=' . $id));
    }

    try {
        $pdo->beginTransaction();

        $recordItems = array_values(array_filter($items, static function ($item) {
            return !empty($item['product_id']);
        }));

        if ($newStatus === 'cancelled' && $order['status'] !== 'cancelled') {
            $restock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($recordItems as $item) {
                $restock->execute([(int) $item['quantity'], (int) $item['product_id']]);
            }
        }

        if ($order['status'] === 'cancelled' && $newStatus !== 'cancelled') {
            $lock = $pdo->prepare('SELECT name, stock FROM products WHERE id = ? FOR UPDATE');
            $deduct = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($recordItems as $item) {
                $lock->execute([(int) $item['product_id']]);
                $product = $lock->fetch();
                if (!$product) {
                    continue;
                }
                $qty = (int) $item['quantity'];
                if ((int) $product['stock'] < $qty) {
                    throw new RuntimeException('لا يمكن إعادة تنشيط الطلب: مخزون «' . $product['name'] . '» غير كافٍ.');
                }
                $deduct->execute([$qty, (int) $item['product_id'], $qty]);
            }
        }

        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
        $pdo->commit();

        flash('success', 'تم تحديث حالة الطلب إلى «' . order_statuses()[$newStatus]['label'] . '».');
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $ex->getMessage());
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'حدث خطأ أثناء تحديث الطلب.');
    }

    redirect(url('admin/order-view.php?id=' . $id));
}

$pageTitle = 'الطلب ' . $order['order_number'];
$pageSubtitle = 'تفاصيل الطلب وتحديث حالته';
$activePage = 'orders';

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-bar">
    <a class="admin-btn admin-btn-ghost" href="<?= url('admin/orders.php') ?>">← كل الطلبات</a>
    <div class="admin-page-bar-actions">
        <?= status_badge($order['status']) ?>
        <a class="admin-btn admin-btn-outline" href="https://wa.me/<?= e(preg_replace('/\D+/', '', setting('whatsapp', '212600000000'))) ?>?text=<?= rawurlencode('مرحباً ' . $order['customer_name'] . '، بخصوص طلبك ' . $order['order_number']) ?>" target="_blank" rel="noopener">مراسلة العميل</a>
    </div>
</div>

<div class="admin-grid-order">
    <div class="admin-order-main">
        <section class="admin-panel admin-panel-flush">
            <div class="admin-panel-head">
                <div>
                    <h2>عناصر الطلب</h2>
                    <p><?= count($items) ?> قطعة — <?= ar_datetime($order['created_at']) ?></p>
                </div>
                <span class="admin-order-number" dir="ltr"><?= e($order['order_number']) ?></span>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table admin-order-items">
                    <thead>
                        <tr><th>القطعة</th><th>السعر</th><th>الكمية</th><th>المجموع</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['product_id']): ?>
                                        <a class="admin-cell-title" href="<?= url('product.php?id=' . (int) $item['product_id']) ?>" target="_blank" rel="noopener"><?= e($item['product_name']) ?></a>
                                    <?php else: ?>
                                        <span class="admin-cell-title"><?= e($item['product_name']) ?></span>
                                        <span class="admin-cell-sub">محذوف من المتجر</span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['size'])): ?>
                                        <span class="admin-cell-sub">المقاس: <?= e($item['size']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= money($item['price']) ?></td>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td><strong><?= money((float) $item['price'] * (int) $item['quantity']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-order-totals">
                <div><span>المجموع الفرعي</span><strong><?= money($order['subtotal']) ?></strong></div>
                <div><span>التوصيل</span><strong><?= (float) $order['shipping'] > 0 ? money($order['shipping']) : 'مجاني' ?></strong></div>
                <div class="is-total"><span>الإجمالي</span><strong><?= money($order['total']) ?></strong></div>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>معلومات العميل والتوصيل</h2><p>بيانات التواصل والعنوان</p></div></div>
            <div class="admin-info-grid">
                <div><span>الاسم</span><strong><?= e($order['customer_name']) ?></strong></div>
                <div><span>الهاتف</span><strong dir="ltr"><a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a></strong></div>
                <div><span>البريد</span><strong dir="ltr"><?= e($order['email'] ?: '—') ?></strong></div>
                <div><span>المدينة</span><strong><?= e($order['city']) ?></strong></div>
                <div class="is-full"><span>العنوان</span><strong><?= e($order['address']) ?></strong></div>
                <?php if ($order['notes']): ?>
                    <div class="is-full"><span>ملاحظات العميل</span><strong><?= e($order['notes']) ?></strong></div>
                <?php endif; ?>
                <div><span>طريقة الدفع</span><strong><?= payment_label($order['payment_method']) ?></strong></div>
                <div><span>آخر تحديث</span><strong><?= ar_datetime($order['updated_at']) ?></strong></div>
            </div>
        </section>
    </div>

    <aside class="admin-order-side">
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>تحديث الحالة</h2><p>سيتم إشعار العميل عند التغيير</p></div></div>
            <form method="post" action="<?= url('admin/order-view.php?id=' . $id) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <label class="admin-field">
                    <span>حالة الطلب</span>
                    <select name="status" class="admin-select">
                        <?php foreach (order_statuses() as $key => $info): ?>
                            <option value="<?= e($key) ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="admin-field-help">عند إلغاء الطلب يُعاد المخزون تلقائياً. وعند إعادة تنشيطه يُخصم مرة أخرى.</p>
                <button class="admin-btn admin-btn-primary admin-btn-block" type="submit">حفظ الحالة</button>
            </form>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>مسار الطلب</h2></div></div>
            <ol class="admin-timeline">
                <?php
                $steps = ['pending' => 'تم الطلب', 'confirmed' => 'تأكيد الطلب', 'shipped' => 'الشحن', 'delivered' => 'التسليم'];
                $keys = array_keys($steps);
                $currentIdx = array_search($order['status'], $keys, true);
                foreach ($steps as $key => $label):
                    $idx = array_search($key, $keys, true);
                ?>
                    <li class="<?= $order['status'] !== 'cancelled' && $idx <= $currentIdx ? 'is-done' : '' ?>">
                        <span></span>
                        <div><strong><?= e($label) ?></strong><small><?= $idx <= $currentIdx && $order['status'] !== 'cancelled' ? 'مكتمل' : 'بانتظار' ?></small></div>
                    </li>
                <?php endforeach; ?>
                <?php if ($order['status'] === 'cancelled'): ?>
                    <li class="is-cancelled"><span></span><div><strong>ملغي</strong><small>تم إلغاء الطلب</small></div></li>
                <?php endif; ?>
            </ol>
        </section>

        <section class="admin-panel admin-panel-soft">
            <h3 class="admin-panel-title">ملخص سريع</h3>
            <ul class="admin-mini-list">
                <li><span>عدد القطع</span><strong><?= array_sum(array_map(static function ($item) { return (int) $item['quantity']; }, $items)) ?></strong></li>
                <li><span>المجموع</span><strong><?= money($order['total']) ?></strong></li>
                <li><span>الحالة</span><strong><?= e(order_statuses()[$order['status']]['label'] ?? $order['status']) ?></strong></li>
            </ul>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
