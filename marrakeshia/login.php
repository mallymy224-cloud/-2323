<?php
require_once __DIR__ . '/includes/functions.php';

$redirectKey = auth_redirect_key();

if (is_logged_in()) {
    // عميلة مسجّلة بالفعل: تُعاد إلى إتمام الطلب إن كانت قادمة منه
    if ($redirectKey === 'checkout' && !is_admin()) {
        redirect(url('checkout.php'));
    }
    redirect(url('account.php'));
}

// الحفاظ على نية «إتمام الطلب» أثناء التنقل بين الدخول وإنشاء الحساب
if ($redirectKey !== '') {
    set_after_auth_target($redirectKey);
} elseif (!is_post()) {
    unset($_SESSION['after_auth']);
}

$mode = 'email';
$identifier = '';
$errors = [];

if (is_post()) {
    verify_csrf();

    $mode = (string) ($_POST['login_mode'] ?? 'email');
    if (!in_array($mode, ['email', 'phone'], true)) {
        $mode = 'email';
    }
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '') {
        $errors[] = $mode === 'phone' ? 'يرجى إدخال رقم الهاتف.' : 'يرجى إدخال البريد الإلكتروني.';
    } elseif ($mode === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'يرجى إدخال بريد إلكتروني صحيح.';
    } elseif ($mode === 'phone' && mb_strlen(normalize_phone($identifier)) < 9) {
        $errors[] = 'يرجى إدخال رقم هاتف صحيح (مثال: 0612345678).';
    }

    if ($password === '') {
        $errors[] = 'يرجى إدخال كلمة المرور.';
    }

    if (!$errors) {
        // صفحة دخول العملاء تقبل حسابات العملاء فقط، ولا تسمح بدخول المدير
        $user = $mode === 'phone'
            ? find_customer_by_phone($identifier)
            : find_customer_by_email($identifier);

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'بيانات الدخول غير صحيحة. تأكدي من رقم الهاتف/البريد وكلمة المرور.';
        } else {
            login_user($user);
            flash('success', 'مرحباً بعودتك، ' . $user['name'] . '!');
            // دخول العملاء: إتمام الطلب إن كانت له نية محفوظة، وإلا صفحة الحساب — never لوحة المدير
            redirect(take_after_auth_target());
        }
    }
}

$pageTitle = 'تسجيل الدخول';
require __DIR__ . '/includes/header.php';
?>

<div class="container section auth-section">
    <div class="auth-split">
        <div class="auth-visual">
            <img src="<?= e(auth_image()) ?>" alt="أناقة مراكشية" width="640" height="780">
            <div class="auth-visual-overlay">
                <span class="brand-tag">MARRAKESHIA</span>
                <h2>أهلاً بك في عائلتنا</h2>
                <p>سجّلي الدخول لمتابعة طلباتك وحفظ عنوانك المفضل لتسوق أسرع.</p>
            </div>
        </div>

        <div class="auth-card">
            <span class="section-kicker">دخول العملاء</span>
            <h1>تسجيل الدخول إلى المتجر</h1>
            <p class="auth-customer-note">هذه الصفحة مخصصة لدخول <strong>العميلات</strong> لمتابعة الطلبات والتسوق. لإدارة المنتجات والطلبات استخدمي <a href="<?= url('admin/login.php') ?>">دخول لوحة التحكم</a>.</p>
            <p class="auth-sub">ليس لديك حساب؟ <a href="<?= e(auth_page_url('register.php', $redirectKey)) ?>">أنشئي حساباً جديداً</a></p>

            <?php if ($redirectKey === 'checkout'): ?>
                <p class="auth-checkout-note">أنتِ على بُعد خطوة من <strong>إتمام الطلب</strong>. سجّلي الدخول أو <a href="<?= e(auth_page_url('register.php', $redirectKey)) ?>">أنشئي حساباً</a> — قطعك محفوظة في السلة ولن تضيع.</p>
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

            <form method="post" action="<?= url('login.php') ?>" class="auth-form" id="customerLoginForm">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e($redirectKey) ?>">

                <div class="auth-tabs" data-auth-tabs>
                    <label class="auth-tab<?= $mode === 'email' ? ' is-active' : '' ?>">
                        <input type="radio" name="login_mode" value="email" <?= $mode === 'email' ? 'checked' : '' ?>>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                        <span>بالبريد الإلكتروني</span>
                    </label>
                    <label class="auth-tab<?= $mode === 'phone' ? ' is-active' : '' ?>">
                        <input type="radio" name="login_mode" value="phone" <?= $mode === 'phone' ? 'checked' : '' ?>>
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 5c0 8 6 14 15 14l1-3-5-2-2 2c-3-1-5-3-6-6l2-2-2-5z"/></svg>
                        <span>برقم الهاتف</span>
                    </label>
                </div>

                <label class="form-field">
                    <span data-identifier-label><?= $mode === 'phone' ? 'رقم الهاتف' : 'البريد الإلكتروني' ?></span>
                    <input type="text" name="identifier" data-identifier-input value="<?= e($identifier) ?>" required
                           autocomplete="<?= $mode === 'phone' ? 'tel' : 'email' ?>"
                           inputmode="<?= $mode === 'phone' ? 'tel' : 'email' ?>"
                           dir="ltr"
                           placeholder="<?= $mode === 'phone' ? '0612345678' : 'name@example.com' ?>">
                    <small class="auth-field-hint" data-identifier-hint><?= $mode === 'phone' ? 'استخدمي رقم الهاتف الذي سجّلتِ به حسابك.' : 'أدخلي بريدك الإلكتروني المسجّل.' ?></small>
                </label>
                <label class="form-field">
                    <span>كلمة المرور</span>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </label>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><?= $redirectKey === 'checkout' ? 'دخول ومتابعة إتمام الطلب' : 'دخول إلى المتجر' ?></button>
            </form>

            <p class="auth-phone-note">الدخول برقم الهاتف يتم بكلمة المرور ولا يُرسل أي رمز SMS في هذه النسخة التجريبية.</p>
            <p class="auth-foot">بالمتابعة أنت توافقين على شروط الاستخدام وسياسة الخصوصية.</p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
