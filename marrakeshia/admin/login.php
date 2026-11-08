<?php
require_once dirname(__DIR__) . '/includes/functions.php';

if (is_admin()) {
    redirect(url('admin/index.php'));
}

$email = '';
$errors = [];

if (is_post()) {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'يرجى إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'بيانات الدخول غير صحيحة، أو أن هذا الحساب ليس حساب مدير.';
        } else {
            login_user($user);
            flash('success', 'مرحباً بك في لوحة التحكم، ' . $user['name'] . '.');
            redirect(url('admin/index.php'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل دخول الإدارة | <?= e(setting('store_name', 'مراكشية')) ?></title>
    <link rel="icon" href="<?= e(site_icon()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
</head>
<body class="admin-login-body">
    <div class="admin-login-card">
        <div class="admin-login-visual">
            <div class="admin-login-pattern" aria-hidden="true"></div>
            <span class="admin-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2"><path d="M24 4c7 7 12 14 12 22a12 12 0 0 1-24 0C12 18 17 11 24 4Z"/><path d="M24 14v20M18 24h12"/></svg>
            </span>
            <h1><?= e(setting('store_name', 'مراكشية')) ?></h1>
            <p>لوحة إدارة المتجر — الطلبات، المنتجات، التصنيفات والإعدادات في مكان واحد.</p>
            <a class="admin-login-store-link" href="<?= url('index.php') ?>">← العودة إلى المتجر</a>
        </div>

        <div class="admin-login-form-wrap">
            <span class="admin-kicker">منطقة محمية — المدير فقط</span>
            <h2>دخول لوحة التحكم</h2>
            <p class="admin-login-sub">هذه الصفحة مخصصة لحساب المدير فقط، ولا تقبل حسابات العملاء.</p>
            <p class="admin-login-role-note">تسجيل دخول العميلات يتم من صفحة منفصلة داخل المتجر ولا يتيح الوصول إلى هنا.</p>

            <?php if ($errors): ?>
                <div class="admin-alert admin-alert-danger">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php $flashHtml = render_flashes(); ?>
            <?php if ($flashHtml !== ''): ?><div class="admin-flashes"><?= $flashHtml ?></div><?php endif; ?>

            <form method="post" action="<?= url('admin/login.php') ?>" class="admin-login-form">
                <?= csrf_field() ?>
                <label class="admin-field">
                    <span>البريد الإلكتروني</span>
                    <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email" placeholder="admin@marrakeshia.com" dir="ltr">
                </label>
                <label class="admin-field">
                    <span>كلمة المرور</span>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </label>
                <button class="admin-btn admin-btn-primary admin-btn-lg admin-btn-block" type="submit">دخول إلى اللوحة</button>
            </form>

            <div class="admin-demo-note">
                <strong>بيانات الدخول الافتراضية</strong>
                <p>البريد: <code dir="ltr">admin@marrakeshia.com</code></p>
                <p>كلمة المرور: <code dir="ltr">admin123</code></p>
                <small>غيّرها من صفحة الإعدادات بعد أول دخول.</small>
            </div>

            <p class="admin-customer-login">أنتِ عميلة وتبحثين عن حسابك؟ <a href="<?= url('login.php') ?>">دخول العملاء إلى المتجر ←</a></p>
        </div>
    </div>
</body>
</html>
