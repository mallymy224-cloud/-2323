<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$summary = cart_summary();
if (!$summary['items']) {
    flash('info', 'سلتك فارغة، أضيفي قطعة واحدة على الأقل قبل إتمام الطلب.');
    redirect(url('cart.php'));
}

// إتمام الطلب وخطوة الدفع متاحان للعميلة المسجّلة فقط
require_checkout_customer();

$user = current_user();
$form = [
    'name'            => $user['name'] ?? '',
    'phone'           => $user['phone'] ?? '',
    'email'           => $user['email'] ?? '',
    'city'            => $user['city'] ?? '',
    'address'         => $user['address'] ?? '',
    'notes'           => '',
    'payment_method'  => 'cod',
];

// استعادة بيانات الطلب التي أدخلتها الزائرة قبل توجيهها لتسجيل الدخول
if (!empty($_SESSION['checkout_form']) && is_array($_SESSION['checkout_form'])) {
    foreach (array_keys($form) as $key) {
        if (isset($_SESSION['checkout_form'][$key]) && is_string($_SESSION['checkout_form'][$key])) {
            $form[$key] = $_SESSION['checkout_form'][$key];
        }
    }
}
unset($_SESSION['checkout_form']);

$errors = [];

if (is_post()) {
    verify_csrf();

    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (mb_strlen($form['name']) < 3) {
        $errors[] = 'يرجى كتابة الاسم الكامل (3 أحرف على الأقل).';
    }
    if (!preg_match('/^[0-9+\s().-]{6,22}$/', $form['phone'])) {
        $errors[] = 'رقم الهاتف غير صحيح. مثال: 0612345678.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح.';
    }
    if (mb_strlen($form['city']) < 2) {
        $errors[] = 'يرجى تحديد المدينة.';
    }
    if (mb_strlen($form['address']) < 8) {
        $errors[] = 'يرجى كتابة عنوان التوصيل بشكل كافٍ (الحي، الشارع، الرقم).';
    }

    $paymentMethod = (string) $form['payment_method'];
    if (!isset(enabled_payment_methods()[$paymentMethod])) {
        $paymentMethod = 'cod';
    }

    /* ------------------------------------------------------------
       بيانات البطاقة (مدى / فيزا)
       ملاحظة أمنية مهمة: هذه الحقول للعرض التجريبي فقط.
       لا تُحفظ في الجلسة ولا في قاعدة البيانات ولا تُرسل لأي جهة.
       في الإنتاج يجب استخدام بوابة دفع بـ Tokenization / Hosted Fields
       وعدم تمرير رقم البطاقة أو CVV إلى سيرفرك إطلاقاً.
       ------------------------------------------------------------ */
    if (payment_method_requires_card($paymentMethod)) {
        $cardName   = trim((string) ($_POST['card_name'] ?? ''));
        $cardNumber = preg_replace('/\D+/', '', (string) ($_POST['card_number'] ?? ''));
        $cardExpiry = trim((string) ($_POST['card_expiry'] ?? ''));
        $cardCvv    = preg_replace('/\D+/', '', (string) ($_POST['card_cvv'] ?? ''));

        if (!preg_match('/^[\p{L}\s\'.-]{3,80}$/u', $cardName)) {
            $errors[] = 'يرجى كتابة اسم حامل البطاقة بشكل صحيح (3 أحرف على الأقل).';
        }
        if (!preg_match('/^\d{12,19}$/', $cardNumber) || !luhn_valid($cardNumber)) {
            $errors[] = 'رقم البطاقة غير صحيح. للتجربة استخدمي 4111 1111 1111 1111.';
        }
        if (!preg_match('#^(0[1-9]|1[0-2])\s*/\s*(\d{2})$#', $cardExpiry, $expParts)) {
            $errors[] = 'تاريخ الانتهاء يجب أن يكون بصيغة MM/YY (مثال: 12/28).';
        } else {
            $expMonth = (int) $expParts[1];
            $expYear  = 2000 + (int) $expParts[2];
            $nowYear  = (int) date('Y');
            $nowMonth = (int) date('n');
            if ($expYear < $nowYear || ($expYear === $nowYear && $expMonth < $nowMonth)) {
                $errors[] = 'انتهت صلاحية البطاقة، استخدمي تاريخاً مستقبلياً.';
            }
        }
        if (!preg_match('/^\d{3,4}$/', $cardCvv)) {
            $errors[] = 'رمز الأمان (CVV) يجب أن يكون 3 أو 4 أرقام.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $orderItems = [];
            $subtotal = 0.0;

            foreach ($summary['items'] as $item) {
                $lock = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
                $lock->execute([(int) $item['id']]);
                $locked = $lock->fetch();

                if (!$locked) {
                    throw new RuntimeException('إحدى القطع لم تعد متوفرة: ' . $item['name']);
                }
                $qty = (int) $item['quantity'];
                if ((int) $locked['stock'] < $qty) {
                    throw new RuntimeException('الكمية المتوفرة من «' . $locked['name'] . '» أقل مما طلبتِ. يرجى تعديل السلة.');
                }

                $price = (float) $locked['price'];
                $orderItems[] = [
                    'product_id' => (int) $locked['id'],
                    'name'       => $locked['name'],
                    'price'      => $price,
                    'quantity'   => $qty,
                    'size'       => (string) ($item['size_name'] ?? ''),
                ];
                $subtotal += $price * $qty;
            }

            $freeThreshold = (float) setting('free_shipping_threshold', '500');
            $shippingCost = (float) setting('shipping_cost', '30');
            $shipping = ($freeThreshold <= 0 || $subtotal >= $freeThreshold) ? 0.0 : $shippingCost;
            $total = $subtotal + $shipping;

            $insert = $pdo->prepare(
                'INSERT INTO orders
                 (order_number, user_id, customer_name, phone, email, city, address, notes,
                  subtotal, shipping, total, payment_method, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $insert->execute([
                'TMP-' . bin2hex(random_bytes(5)),
                $user['id'] ?? null,
                $form['name'],
                $form['phone'],
                $form['email'] !== '' ? $form['email'] : null,
                $form['city'],
                $form['address'],
                $form['notes'] !== '' ? $form['notes'] : null,
                $subtotal,
                $shipping,
                $total,
                $paymentMethod,
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $orderNumber = order_number_for($orderId);

            $updateNumber = $pdo->prepare('UPDATE orders SET order_number = ? WHERE id = ?');
            $updateNumber->execute([$orderNumber, $orderId]);

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, quantity, size) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $decreaseStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

            foreach ($orderItems as $item) {
                $insertItem->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $item['size'] !== '' ? $item['size'] : null,
                ]);
                $decreaseStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($decreaseStock->rowCount() < 1) {
                    throw new RuntimeException('تعذر تحديث المخزون لقطعة «' . $item['name'] . '».');
                }
            }

            if ($user) {
                $profile = $pdo->prepare('UPDATE users SET phone = ?, city = ?, address = ? WHERE id = ?');
                $profile->execute([$form['phone'], $form['city'], $form['address'], (int) $user['id']]);
            }

            $pdo->commit();

            cart_clear();
            $_SESSION['last_order_id'] = $orderId;
            redirect(url('order-success.php'));
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'حدث خطأ غير متوقع أثناء إتمام الطلب. يرجى المحاولة مرة أخرى.';
        }
    }
}

$pageTitle = 'إتمام الطلب';
require __DIR__ . '/includes/header.php';

$cities = ['مراكش', 'الدار البيضاء', 'الرباط', 'فاس', 'طنجة', 'أكادير', 'مكناس', 'وجدة', 'تطوان', 'القنيطرة', 'سلا', 'الجديدة', 'بني ملال', 'الناظور', 'خريبكة', 'آسفي', 'العيون', 'الصويرة', 'تازة', 'سطات', 'المحمدية', 'الفقيه بن صالح'];
?>

<div class="page-hero page-hero-slim">
    <div class="container">
        <nav class="breadcrumb" aria-label="مسار التنقل">
            <a href="<?= url('index.php') ?>">الرئيسية</a>
            <span aria-hidden="true">/</span>
            <a href="<?= url('cart.php') ?>">السلة</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">إتمام الطلب</span>
        </nav>
        <h1>إتمام الطلب</h1>
        <p>خطوة أخيرة فقط، وستصلك قطعتك مغلّفة كهدية.</p>
    </div>
</div>

<div class="container section section-checkout">
    <div class="checkout-steps" aria-hidden="true">
        <span class="step is-done"><i>1</i> السلة</span>
        <span class="step-line"></span>
        <span class="step is-active"><i>2</i> معلومات التوصيل</span>
        <span class="step-line"></span>
        <span class="step"><i>3</i> تأكيد الطلب</span>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <div>
                <strong>يرجى مراجعة البيانات:</strong>
                <ul class="alert-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">
        <form class="checkout-form" id="checkoutForm" method="post" action="<?= url('checkout.php') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-card">
                <div class="form-card-head">
                    <span class="form-step">1</span>
                    <div>
                        <h2>معلومات التوصيل</h2>
                        <p>سنستخدم هذه المعلومات لتوصيل طلبك.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <label class="form-field">
                        <span>الاسم الكامل <b>*</b></span>
                        <input type="text" name="name" value="<?= e($form['name']) ?>" required autocomplete="name" placeholder="مثال: سلمى العمراني">
                    </label>

                    <label class="form-field">
                        <span>رقم الهاتف <b>*</b></span>
                        <input type="tel" name="phone" value="<?= e($form['phone']) ?>" required autocomplete="tel" inputmode="tel" placeholder="0612345678" dir="ltr">
                    </label>

                    <label class="form-field">
                        <span>البريد الإلكتروني <small>(اختياري)</small></span>
                        <input type="email" name="email" value="<?= e($form['email']) ?>" autocomplete="email" placeholder="name@example.com" dir="ltr">
                    </label>

                    <label class="form-field">
                        <span>المدينة <b>*</b></span>
                        <input type="text" name="city" value="<?= e($form['city']) ?>" required autocomplete="address-level2" list="citiesList" placeholder="مراكش">
                        <datalist id="citiesList">
                            <?php foreach ($cities as $city): ?>
                                <option value="<?= e($city) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </label>

                    <label class="form-field form-field-full">
                        <span>عنوان التوصيل <b>*</b></span>
                        <input type="text" name="address" value="<?= e($form['address']) ?>" required autocomplete="street-address" placeholder="الحي، الشارع، رقم المنزل / الطابق">
                    </label>

                    <label class="form-field form-field-full">
                        <span>ملاحظات على الطلب <small>(اختياري)</small></span>
                        <textarea name="notes" rows="3" placeholder="وقت التوصيل المفضل، طلب تغليف خاص…"><?= e($form['notes']) ?></textarea>
                    </label>
                </div>
            </div>

            <div class="form-card">
                <div class="form-card-head">
                    <span class="form-step">2</span>
                    <div>
                        <h2>طريقة الدفع</h2>
                        <p>اختاري الطريقة المناسبة لك.</p>
                    </div>
                </div>

                <div class="payment-options">
                    <?php
                    $enabledMethods = enabled_payment_methods();
                    $selectedMethod = isset($enabledMethods[$form['payment_method']])
                        ? $form['payment_method']
                        : (string) array_key_first($enabledMethods);
                    $cardActive = payment_method_requires_card($selectedMethod);
                    ?>
                    <?php foreach ($enabledMethods as $key => $info): ?>
                        <?php $selected = $selectedMethod === $key; ?>
                        <label class="payment-option<?= $selected ? ' is-selected' : '' ?>" data-payment-option>
                            <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $selected ? 'checked' : '' ?> <?= !empty($info['card']) ? 'data-requires-card="1"' : '' ?>>
                            <span class="payment-icon"><?= $info['icon'] ?></span>
                            <span class="payment-info">
                                <strong><?= e($info['label']) ?></strong>
                                <small><?= e($info['desc']) ?></small>
                            </span>
                            <span class="payment-check" aria-hidden="true">✓</span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="card-details" id="cardDetails"<?= $cardActive ? '' : ' hidden' ?>>
                    <div class="card-details-head">
                        <span class="card-details-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 9.5h19M6 15h4"/></svg>
                        </span>
                        <div>
                            <strong>بيانات البطاقة</strong>
                            <small>تُستخدم للتحقق التجريبي فقط — لا تُحفظ ولا تُرسل لأي جهة.</small>
                        </div>
                    </div>

                    <div class="card-fields">
                        <label class="form-field form-field-full">
                            <span>اسم حامل البطاقة <b>*</b></span>
                            <input type="text" name="card_name" autocomplete="cc-name" placeholder="كما يظهر على البطاقة" maxlength="80" <?= $cardActive ? 'required' : 'disabled' ?>>
                        </label>

                        <label class="form-field form-field-full">
                            <span>رقم البطاقة <b>*</b></span>
                            <input type="text" name="card_number" data-card-number inputmode="numeric" autocomplete="cc-number" dir="ltr" placeholder="0000 0000 0000 0000" maxlength="23" <?= $cardActive ? 'required' : 'disabled' ?>>
                        </label>

                        <label class="form-field">
                            <span>تاريخ الانتهاء <b>*</b></span>
                            <input type="text" name="card_expiry" data-card-expiry inputmode="numeric" autocomplete="cc-exp" dir="ltr" placeholder="MM/YY" maxlength="7" <?= $cardActive ? 'required' : 'disabled' ?>>
                        </label>

                        <label class="form-field">
                            <span>رمز الأمان CVV <b>*</b></span>
                            <input type="text" name="card_cvv" data-card-cvv inputmode="numeric" autocomplete="cc-csc" dir="ltr" placeholder="123" maxlength="4" <?= $cardActive ? 'required' : 'disabled' ?>>
                        </label>
                    </div>

                    <div class="card-demo-note">
                        <strong>للتجربة فقط:</strong>
                        رقم البطاقة <code dir="ltr">4111 1111 1111 1111</code>، تاريخ مستقبلي مثل <code dir="ltr">12/28</code>، وأي CVV من 3 أرقام.
                        إن ظهرت رسالة خطأ، أعidi إدخال البيانات من جديد (لا نُعيد عرضها لأسباب أمنية).
                    </div>
                </div>

                <p class="payment-note">ملاحظة: الدفع الإلكتروني (مدى / فيزا) تجريبي في هذه النسخة ولا يتم تحصيل أي مبلغ فعلي، ولا تُحفظ بيانات البطاقة.</p>
            </div>

            <div class="checkout-submit-mobile">
                <button class="btn btn-primary btn-lg btn-block" type="submit">تأكيد الطلب — <?= money($summary['total']) ?></button>
            </div>
        </form>

        <aside class="checkout-summary">
            <div class="summary-card">
                <h2>طلبك</h2>
                <ul class="checkout-items">
                    <?php foreach ($summary['items'] as $item): ?>
                        <li>
                            <span class="checkout-thumb">
                                <img src="<?= product_image($item['image']) ?>" alt="<?= e($item['name']) ?>" width="64" height="80" loading="lazy">
                                <span class="checkout-qty"><?= (int) $item['quantity'] ?></span>
                            </span>
                            <span class="checkout-item-info">
                                <strong><?= e($item['name']) ?></strong>
                                <small><?= money($item['price']) ?></small>
                                <?php if (!empty($item['size_name'])): ?>
                                    <small class="checkout-item-size">المقاس: <?= e($item['size_name']) ?></small>
                                <?php endif; ?>
                            </span>
                            <span class="checkout-line-total"><?= money($item['line_total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <dl class="summary-lines">
                    <div><dt>المجموع الفرعي</dt><dd><?= money($summary['subtotal']) ?></dd></div>
                    <div><dt>التوصيل</dt><dd><?= $summary['shipping'] > 0 ? money($summary['shipping']) : '<span class="free-tag">مجاني</span>' ?></dd></div>
                    <div class="summary-total"><dt>الإجمالي</dt><dd><?= money($summary['total']) ?></dd></div>
                </dl>

                <button class="btn btn-primary btn-lg btn-block checkout-submit-desktop" type="submit" form="checkoutForm">
                    تأكيد الطلب
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                </button>

                <a class="back-cart" href="<?= url('cart.php') ?>">← تعديل السلة</a>
                <p class="summary-note">بتأكيد الطلب أنت توافقين على سياسة الاستبدال خلال 48 ساعة.</p>
            </div>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
