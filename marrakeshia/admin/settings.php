<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$values = settings();
$errors = [];
$passwordErrors = [];

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $admin['password'])) {
            $passwordErrors[] = 'كلمة المرور الحالية غير صحيحة.';
        }
        if (mb_strlen($newPassword) < 8) {
            $passwordErrors[] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل.';
        }
        if ($newPassword !== $confirmPassword) {
            $passwordErrors[] = 'كلمتا المرور الجديدتان غير متطابقتين.';
        }

        if (!$passwordErrors) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $admin['id']]);
            flash('success', 'تم تغيير كلمة المرور بنجاح.');
            redirect(url('admin/settings.php'));
        }
    } else {
        $fields = [
            'store_name', 'tagline', 'announcement', 'phone', 'whatsapp', 'email', 'address',
            'instagram', 'facebook', 'shipping_cost', 'free_shipping_threshold', 'currency', 'about_text',
        ];
        foreach ($fields as $field) {
            $values[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        if (mb_strlen($values['store_name']) < 2) {
            $errors[] = 'اسم المتجر مطلوب.';
        }
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صحيح.';
        }
        if (!is_numeric($values['shipping_cost']) || (float) $values['shipping_cost'] < 0) {
            $errors[] = 'تكلفة التوصيل يجب أن تكون رقماً موجباً.';
        }
        if (!is_numeric($values['free_shipping_threshold']) || (float) $values['free_shipping_threshold'] < 0) {
            $errors[] = 'حد التوصيل المجاني يجب أن يكون رقماً موجباً.';
        }
        if ($values['currency'] === '') {
            $values['currency'] = 'د.م.';
        }

        // طرق الدفع المفعّلة
        $allMethodKeys = array_keys(payment_methods());
        $postedMethods = isset($_POST['payment_methods']) && is_array($_POST['payment_methods'])
            ? array_map('strval', $_POST['payment_methods'])
            : [];
        $enabledMethods = array_values(array_intersect($postedMethods, $allMethodKeys));
        if (!$enabledMethods) {
            $enabledMethods = ['cod'];
        }
        $values['payment_methods'] = implode(',', $enabledMethods);

        // صورة الواجهة
        $currentHero = (string) ($values['hero_image'] ?? '');
        $heroUpload = upload_site_image($_FILES['hero_image'] ?? [], $currentHero);
        if (!$heroUpload['ok']) {
            $errors[] = $heroUpload['error'];
        }
        $heroImage = (string) ($heroUpload['image'] ?? '');
        if (!empty($_POST['remove_hero_image'])
            && (!isset($_FILES['hero_image']['error']) || $_FILES['hero_image']['error'] === UPLOAD_ERR_NO_FILE)) {
            $heroImage = '';
        }
        $values['hero_image'] = $heroImage;

        // صورة صفحات الدخول وإنشاء الحساب
        $currentAuth = (string) ($values['auth_image'] ?? '');
        $authUpload = upload_site_image($_FILES['auth_image'] ?? [], $currentAuth, 'auth');
        if (!$authUpload['ok']) {
            $errors[] = $authUpload['error'];
        }
        $authImage = (string) ($authUpload['image'] ?? '');
        if (!empty($_POST['remove_auth_image'])
            && (!isset($_FILES['auth_image']['error']) || $_FILES['auth_image']['error'] === UPLOAD_ERR_NO_FILE)) {
            $authImage = '';
        }
        $values['auth_image'] = $authImage;

        // أيقونة الموقع + أيقونة حقل البحث
        foreach (['favicon', 'search_icon'] as $iconKey) {
            $current = (string) ($values[$iconKey] ?? '');
            $upload = upload_site_image(
                $_FILES[$iconKey] ?? [],
                $current,
                $iconKey === 'favicon' ? 'icon' : 'searchicon'
            );
            if (!$upload['ok']) {
                $errors[] = $upload['error'];
            }
            $iconValue = (string) ($upload['image'] ?? '');
            $removeKey = 'remove_' . $iconKey;
            if (!empty($_POST[$removeKey])
                && (!isset($_FILES[$iconKey]['error']) || $_FILES[$iconKey]['error'] === UPLOAD_ERR_NO_FILE)) {
                $iconValue = '';
            }
            $values[$iconKey] = $iconValue;
        }

        if (!$errors) {
            $upsert = $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach (array_merge($fields, ['hero_image', 'auth_image', 'favicon', 'search_icon', 'payment_methods']) as $field) {
                $upsert->execute([$field, $values[$field]]);
            }
            flash('success', 'تم حفظ إعدادات المتجر بنجاح.');
            redirect(url('admin/settings.php'));
        }
    }
}

$pageTitle = 'الإعدادات';
$pageSubtitle = 'بيانات المتجر، التواصل، الشحن وكلمة المرور';
$activePage = 'settings';

require __DIR__ . '/includes/header.php';
?>

<?php if ($errors): ?>
    <div class="admin-alert admin-alert-danger">
        <strong>يرجى تصحيح ما يلي:</strong>
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form class="admin-settings-form" method="post" action="<?= url('admin/settings.php') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="admin-settings-main">
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>هوية المتجر</h2><p>الاسم والشعار النصي الظاهر في الموقع</p></div></div>
            <div class="admin-field-grid">
                <label class="admin-field">
                    <span>اسم المتجر <b>*</b></span>
                    <input type="text" name="store_name" value="<?= e($values['store_name'] ?? '') ?>" required>
                </label>
                <label class="admin-field">
                    <span>الشعار النصي</span>
                    <input type="text" name="tagline" value="<?= e($values['tagline'] ?? '') ?>" placeholder="أناقة مغربية أصيلة">
                </label>
                <label class="admin-field admin-field-full">
                    <span>شريط الإعلان العلوي</span>
                    <input type="text" name="announcement" value="<?= e($values['announcement'] ?? '') ?>" placeholder="توصيل مجاني للطلبات فوق 500 د.م.">
                </label>
                <label class="admin-field admin-field-full">
                    <span>نبذة عن المتجر</span>
                    <textarea name="about_text" rows="5"><?= e($values['about_text'] ?? '') ?></textarea>
                    <small class="admin-field-help">تظهر في الصفحة الرئيسية وفي تذييل الموقع.</small>
                </label>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>التواصل والشبكات</h2><p>تظهر هذه المعلومات في تذييل الموقع</p></div></div>
            <div class="admin-field-grid">
                <label class="admin-field">
                    <span>رقم الهاتف</span>
                    <input type="text" name="phone" value="<?= e($values['phone'] ?? '') ?>" dir="ltr" placeholder="+212 6 12 34 56 78">
                </label>
                <label class="admin-field">
                    <span>رقم واتساب</span>
                    <input type="text" name="whatsapp" value="<?= e($values['whatsapp'] ?? '') ?>" dir="ltr" placeholder="212612345678">
                    <small class="admin-field-help">بصيغة دولية بدون + أو مسافات.</small>
                </label>
                <label class="admin-field">
                    <span>البريد الإلكتروني</span>
                    <input type="email" name="email" value="<?= e($values['email'] ?? '') ?>" dir="ltr">
                </label>
                <label class="admin-field">
                    <span>العنوان</span>
                    <input type="text" name="address" value="<?= e($values['address'] ?? '') ?>" placeholder="مراكش، المغرب">
                </label>
                <label class="admin-field">
                    <span>رابط إنستغرام</span>
                    <input type="url" name="instagram" value="<?= e($values['instagram'] ?? '') ?>" dir="ltr">
                </label>
                <label class="admin-field">
                    <span>رابط فيسبوك</span>
                    <input type="url" name="facebook" value="<?= e($values['facebook'] ?? '') ?>" dir="ltr">
                </label>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>الشحن والعملة</h2><p>تُطبّق تلقائياً على كل الطلبات الجديدة</p></div></div>
            <div class="admin-field-grid">
                <label class="admin-field">
                    <span>تكلفة التوصيل</span>
                    <input type="number" name="shipping_cost" min="0" step="0.01" value="<?= e($values['shipping_cost'] ?? '30') ?>">
                    <small class="admin-field-help">بالدرهم. استخدمي 0 للتوصيل المجاني الدائم.</small>
                </label>
                <label class="admin-field">
                    <span>حد التوصيل المجاني</span>
                    <input type="number" name="free_shipping_threshold" min="0" step="0.01" value="<?= e($values['free_shipping_threshold'] ?? '500') ?>">
                    <small class="admin-field-help">توصيل مجاني للطلبات التي تساوي أو تفوق هذا المبلغ.</small>
                </label>
                <label class="admin-field">
                    <span>العملة</span>
                    <input type="text" name="currency" value="<?= e($values['currency'] ?? 'د.م.') ?>">
                </label>
            </div>
        </section>
    </div>

    <aside class="admin-settings-side">
        <section class="admin-panel admin-panel-soft">
            <h3 class="admin-panel-title">معاينة سريعة</h3>
            <ul class="admin-mini-list">
                <li><span>المتجر</span><strong><?= e($values['store_name'] ?? '') ?></strong></li>
                <li><span>الهاتف</span><strong dir="ltr"><?= e($values['phone'] ?? '—') ?></strong></li>
                <li><span>التوصيل</span><strong><?= money($values['shipping_cost'] ?? 0) ?></strong></li>
                <li><span>الحد المجاني</span><strong><?= money($values['free_shipping_threshold'] ?? 0) ?></strong></li>
            </ul>
            <button class="admin-btn admin-btn-primary admin-btn-block admin-btn-lg" type="submit">حفظ الإعدادات</button>
            <a class="admin-btn admin-btn-ghost admin-btn-block" href="<?= url('index.php') ?>" target="_blank" rel="noopener">معاينة المتجر</a>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>أمان الحساب</h2><p>غيّري كلمة مرور المدير</p></div></div>
            <p class="admin-field-help">آخر دخول بحساب: <strong dir="ltr"><?= e($admin['email']) ?></strong></p>
        </section>

        <?php
        $siteImage = static function (string $value, string $fallback): string {
            if ($value === '') {
                return $fallback;
            }
            return preg_match('#^https?://#i', $value)
                ? $value
                : url('assets/images/uploads/' . basename($value));
        };
        $heroValue = (string) ($values['hero_image'] ?? '');
        $heroPreview = $siteImage($heroValue, url('assets/images/hero.svg'));
        $authValue = (string) ($values['auth_image'] ?? '');
        $authPreview = $siteImage($authValue, url('assets/images/products/p2.svg'));
        $faviconValue = (string) ($values['favicon'] ?? '');
        $faviconPreview = $siteImage($faviconValue, url('assets/images/favicon.svg'));
        $searchIconValue = (string) ($values['search_icon'] ?? '');
        $searchIconPreview = $searchIconValue === '' ? '' : $siteImage($searchIconValue, '');
        $enabledSettings = array_filter(array_map('trim', explode(',', (string) ($values['payment_methods'] ?? 'cod,mada,visa'))));
        ?>
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>صورة الواجهة الرئيسية</h2><p>تظهر في الصفحة الرئيسية فقط</p></div></div>
            <div class="admin-image-preview admin-image-preview-sm" id="heroPreview">
                <img src="<?= e($heroPreview) ?>" alt="معاينة صورة الواجهة" data-preview-img>
            </div>
            <label class="admin-file-field">
                <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" data-image-input data-preview-target="#heroPreview">
                <span>رفع صورة جديدة</span>
            </label>
            <?php if ($heroValue !== ''): ?>
                <label class="admin-check admin-check-danger">
                    <input type="checkbox" name="remove_hero_image" value="1">
                    <span><strong>العودة للصورة الافتراضية</strong></span>
                </label>
            <?php endif; ?>
            <small class="admin-field-help">JPG / PNG / WEBP / SVG — أقل من 5 ميغابايت. يُفضّل صورة عمودية 4:5.</small>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>صورة الدخول والتسجيل</h2><p>تظهر في صفحتي تسجيل الدخول وإنشاء الحساب</p></div></div>
            <div class="admin-image-preview admin-image-preview-sm" id="authPreview">
                <img src="<?= e($authPreview) ?>" alt="معاينة صورة الدخول والتسجيل" data-preview-img>
            </div>
            <label class="admin-file-field">
                <input type="file" name="auth_image" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" data-image-input data-preview-target="#authPreview">
                <span>رفع صورة جديدة</span>
            </label>
            <?php if ($authValue !== ''): ?>
                <label class="admin-check admin-check-danger">
                    <input type="checkbox" name="remove_auth_image" value="1">
                    <span><strong>العودة للصورة الافتراضية</strong></span>
                </label>
            <?php endif; ?>
            <small class="admin-field-help">تُستخدم في <strong>login.php</strong> و<strong>register.php</strong> فقط، بشكل مستقل عن صورة الواجهة الرئيسية.</small>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>الأيقونات</h2><p>أيقونة الموقع وأيقونة حقل البحث</p></div></div>

            <div class="admin-icon-row">
                <div class="admin-icon-preview" id="faviconPreview">
                    <img src="<?= e($faviconPreview) ?>" alt="معاينة أيقونة الموقع" data-preview-img>
                </div>
                <div class="admin-icon-fields">
                    <strong>أيقونة الموقع (Favicon)</strong>
                    <small>تظهر في تبويب المتصفح ونتائج البحث.</small>
                    <label class="admin-file-field">
                        <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" data-image-input data-preview-target="#faviconPreview">
                        <span>رفع أيقونة</span>
                    </label>
                    <?php if ($faviconValue !== ''): ?>
                        <label class="admin-check admin-check-danger">
                            <input type="checkbox" name="remove_favicon" value="1">
                            <span><strong>الافتراضية</strong></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-icon-row">
                <div class="admin-icon-preview" id="searchIconPreview">
                    <?php if ($searchIconValue !== ''): ?>
                        <img src="<?= e($searchIconPreview) ?>" alt="معاينة أيقونة البحث" data-preview-img>
                    <?php else: ?>
                        <svg class="admin-icon-default" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" data-preview-img aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <?php endif; ?>
                </div>
                <div class="admin-icon-fields">
                    <strong>أيقونة حقل البحث</strong>
                    <small>تظهر داخل مربع البحث في رأس المتجر.</small>
                    <label class="admin-file-field">
                        <input type="file" name="search_icon" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" data-image-input data-preview-target="#searchIconPreview">
                        <span>رفع أيقونة</span>
                    </label>
                    <?php if ($searchIconValue !== ''): ?>
                        <label class="admin-check admin-check-danger">
                            <input type="checkbox" name="remove_search_icon" value="1">
                            <span><strong>الافتراضية</strong></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            <small class="admin-field-help">يُنصح بأيقونة مربعة شفافة (PNG أو SVG) للاثنتين.</small>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>طرق الدفع</h2><p>حدّدي ما يظهر للعميلة عند إتمام الطلب</p></div></div>
            <div class="admin-payment-toggles">
                <?php foreach (payment_methods() as $key => $info): ?>
                    <label class="admin-check">
                        <input type="checkbox" name="payment_methods[]" value="<?= e($key) ?>" <?= in_array($key, $enabledSettings, true) ? 'checked' : '' ?>>
                        <span><strong><?= e($info['label']) ?></strong><small><?= e($info['desc']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small class="admin-field-help">الدفع الإلكتروني تجريبي ولا يتصل بأي بوابة دفع حقيقية.</small>
        </section>
    </aside>
</form>

<div class="admin-grid-2">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>تغيير كلمة المرور</h2><p>ننصح بتغيير كلمة المرور الافتراضية فوراً</p></div></div>

        <?php if ($passwordErrors): ?>
            <div class="admin-alert admin-alert-danger">
                <?php foreach ($passwordErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/settings.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="admin-field-grid">
                <label class="admin-field admin-field-full">
                    <span>كلمة المرور الحالية</span>
                    <input type="password" name="current_password" required autocomplete="current-password">
                </label>
                <label class="admin-field">
                    <span>كلمة المرور الجديدة</span>
                    <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
                </label>
                <label class="admin-field">
                    <span>تأكيد كلمة المرور</span>
                    <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
                </label>
            </div>
            <div class="admin-form-actions">
                <button class="admin-btn admin-btn-outline" type="submit">تحديث كلمة المرور</button>
            </div>
        </form>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>معلومات النظام</h2><p>بيانات تقنية مفيدة</p></div></div>
        <ul class="admin-mini-list">
            <li><span>إصدار PHP</span><strong dir="ltr"><?= e(PHP_VERSION) ?></strong></li>
            <li><span>قاعدة البيانات</span><strong dir="ltr"><?= e(DB_NAME) ?></strong></li>
            <li><span>رابط المشروع</span><strong dir="ltr"><?= e(BASE_URL ?: '/') ?></strong></li>
            <li><span>المنطقة الزمنية</span><strong dir="ltr"><?= e(date_default_timezone_get()) ?></strong></li>
        </ul>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
