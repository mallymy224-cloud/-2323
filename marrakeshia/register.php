<?php
require_once __DIR__ . '/includes/functions.php';

$redirectKey = auth_redirect_key();

if (is_logged_in()) {
    if ($redirectKey === 'checkout' && !is_admin()) {
        redirect(url('checkout.php'));
    }
    redirect(url('account.php'));
}

if ($redirectKey !== '') {
    set_after_auth_target($redirectKey);
} elseif (!is_post()) {
    unset($_SESSION['after_auth']);
}

$form = ['name' => '', 'email' => '', 'phone' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();

    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($form['name']) < 3) {
        $errors[] = 'يرجى كتابة الاسم الكامل (3 أحرف على الأقل).';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'يرجى إدخال بريد إلكتروني صحيح.';
    }
    $normalizedPhone = normalize_phone($form['phone']);
    if ($form['phone'] === '' || !preg_match('/^[0-9+\s().-]{6,22}$/', $form['phone'])) {
        $errors[] = 'يرجى إدخال رقم هاتف صحيح.';
    } elseif (mb_strlen($normalizedPhone) < 9 || strlen($normalizedPhone) > 15) {
        $errors[] = 'رقم الهاتف غير صحيح. مثال: 0612345678.';
    }
    if (mb_strlen($password) < 6) {
        $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.';
    }
    if ($password !== $confirm) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$form['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'هذا البريد الإلكتروني مسجل مسبقاً. جرّبي تسجيل الدخول.';
        }

        // منع تكرار رقم الهاتف حتى يكون الدخول به unambiguous
        $phoneCheck = db()->query("SELECT id, phone FROM users WHERE role = 'customer' AND phone IS NOT NULL AND phone <> ''");
        foreach ($phoneCheck->fetchAll() as $row) {
            if (normalize_phone($row['phone']) === $normalizedPhone) {
                $errors[] = 'رقم الهاتف مستخدم مسبقاً. جرّبي تسجيل الدخول برقم الهاتف.';
                break;
            }
        }
    }

    if (!$errors) {
        // الدور دائماً customer صراحةً: لا تُمنح أي صلاحية مدير عند إنشاء الحساب
        $insert = db()->prepare(
            'INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, "customer")'
        );
        $insert->execute([
            $form['name'],
            $form['email'],
            $normalizedPhone,
            password_hash($password, PASSWORD_DEFAULT),
        ]);

        $newUser = [
            'id'   => (int) db()->lastInsertId(),
            'role' => 'customer',
            'name' => $form['name'],
        ];
        login_user($newUser); // يضبط user_role = customer داخل الجلسة
        flash('success', 'تم إنشاء حسابك بنجاح، مرحباً بك في مراكشية ' . $form['name'] . '!');
        redirect(take_after_auth_target());
    }
}

$pageTitle = 'إنشاء حساب';
require __DIR__ . '/includes/header.php';
?>

<div class="container section auth-section">
    <div class="auth-split">
        <div class="auth-visual">
            <img src="<?= e(auth_image()) ?>" alt="أناقة مراكشية" width="640" height="780">
            <div class="auth-visual-overlay">
                <span class="brand-tag">MARRAKESHIA</span>
                <h2>انضمي إلى مراكشية</h2>
                <p>احفظي عنوانك، تابعي طلباتك، واستفيدي من عروض خاصة لأعضاء المتجر.</p>
            </div>
        </div>

        <div class="auth-card">
            <span class="section-kicker">حساب جديد</span>
            <h1>إنشاء حساب</h1>
            <p class="auth-sub">لديك حساب بالفعل؟ <a href="<?= e(auth_page_url('login.php', $redirectKey)) ?>">تسجيل الدخول</a></p>

            <?php if ($redirectKey === 'checkout'): ?>
                <p class="auth-checkout-note">أنشئي حساباً لتعودي مباشرة إلى <strong>إتمام الطلب</strong> — قطعك محفوظة في السلة.</p>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="alert-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('register.php') ?>" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e($redirectKey) ?>">
                <label class="form-field">
                    <span>الاسم الكامل</span>
                    <input type="text" name="name" value="<?= e($form['name']) ?>" required autocomplete="name" placeholder="مثال: سلمى العمراني">
                </label>
                <label class="form-field">
                    <span>البريد الإلكتروني</span>
                    <input type="email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email" placeholder="name@example.com" dir="ltr">
                </label>
                <label class="form-field">
                    <span>رقم الهاتف <b>*</b></span>
                    <input type="tel" name="phone" value="<?= e($form['phone']) ?>" required autocomplete="tel" placeholder="0612345678" dir="ltr">
                    <small class="auth-field-hint">سيُستخدم لاحقاً لتسجيل الدخول برقم الهاتف.</small>
                </label>
                <div class="form-grid">
                    <label class="form-field">
                        <span>كلمة المرور</span>
                        <input type="password" name="password" required autocomplete="new-password" placeholder="6 أحرف على الأقل">
                    </label>
                    <label class="form-field">
                        <span>تأكيد كلمة المرور</span>
                        <input type="password" name="password_confirm" required autocomplete="new-password" placeholder="أعيدي كتابتها">
                    </label>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><?= $redirectKey === 'checkout' ? 'إنشاء الحساب ومتابعة الطلب' : 'إنشاء الحساب' ?></button>
            </form>

            <p class="auth-foot">بإنشاء الحساب أنت توافقين على شروط الاستخدام وسياسة الخصوصية.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
