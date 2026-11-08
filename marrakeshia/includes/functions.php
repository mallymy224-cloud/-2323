<?php
/**
 * Marrakeshia — دوال مساعدة عامة
 */

require_once __DIR__ . '/../config/database.php';

/* ============================================================
 *  عرض آمن وتوجيه
 * ============================================================ */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
}

function excerpt(?string $text, int $length = 120): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
}

/* ============================================================
 *  الرسائل المؤقتة (Flash)
 * ============================================================ */

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    return null;
}

function render_flashes(): string
{
    $html = '';
    $map = ['success' => 'success', 'error' => 'danger', 'info' => 'info'];
    foreach ($map as $key => $class) {
        $message = flash($key);
        if ($message) {
            $html .= '<div class="alert alert-' . $class . '" role="alert">'
                . '<span>' . e($message) . '</span>'
                . '<button type="button" class="alert-close" data-dismiss aria-label="إغلاق">&times;</button>'
                . '</div>';
        }
    }
    return $html;
}

/* ============================================================
 *  حماية النماذج CSRF
 * ============================================================ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(bool $jsonResponse = false): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        if ($jsonResponse) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'انتهت صلاحية الجلسة، يرجى تحديث الصفحة والمحاولة من جديد.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        http_response_code(419);
        die('انتهت صلاحية الجلسة، يرجى العودة وتحديث الصفحة.');
    }
}

/* ============================================================
 *  الإعدادات والتنسيق
 * ============================================================ */

function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function currency(): string
{
    return setting('currency', 'د.م.');
}

function money($amount): string
{
    $amount = (float) $amount;
    $formatted = ($amount == floor($amount))
        ? number_format($amount, 0, '.', ',')
        : number_format($amount, 2, '.', ',');
    return $formatted . ' ' . currency();
}

function ar_date(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل', 5 => 'مايو', 6 => 'يونيو',
        7 => 'يوليوز', 8 => 'غشت', 9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر',
    ];
    $ts = strtotime($datetime);
    if (!$ts) {
        return '—';
    }
    return date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function ar_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return ar_date($datetime) . ' — ' . date('H:i', $ts);
}

/* ============================================================
 *  المستخدمون
 * ============================================================ */

function current_user(): ?array
{
    static $user = null;

    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $found = $stmt->fetch();
        if ($found) {
            $user = $found;
        } else {
            unset($_SESSION['user_id'], $_SESSION['user_role']);
            return null;
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['user_role']);
    session_regenerate_id(true);
}

/**
 * توحيد صيغة رقم الهاتف للمقارنة
 * 0612345678 / +212612345678 / 00212612345678 ← كلها تُعاد 0612345678
 */
function normalize_phone(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '00212')) {
        $digits = '0' . substr($digits, 5);
    } elseif (str_starts_with($digits, '212') && strlen($digits) >= 11) {
        $digits = '0' . substr($digits, 3);
    }
    return $digits;
}

function find_customer_by_email(string $email): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'customer' LIMIT 1");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function find_customer_by_phone(string $phone): ?array
{
    $needle = normalize_phone($phone);
    if ($needle === '') {
        return null;
    }
    // في الإنتاج يُفضّل تخزين رقم مُطبّع في عمود مفهرس بدل مقارنة كل العملاء
    $stmt = db()->query("SELECT * FROM users WHERE role = 'customer' AND phone IS NOT NULL AND phone <> ''");
    foreach ($stmt->fetchAll() as $user) {
        if (normalize_phone($user['phone']) === $needle) {
            return $user;
        }
    }
    return null;
}

/* ============================================================
 *  حماية إتمام الطلب والعودة بعد تسجيل الدخول
 * ============================================================ */

/** القيمة الوحيدة المسموح بها لمعامل ?redirect بعد الدخول */
function auth_redirect_key(): string
{
    $raw = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
    return is_string($raw) && $raw === 'checkout' ? 'checkout' : '';
}

/** رابط صفحة دخول/تسجيل مع الحفاظ على الوجهة المقصودة */
function auth_page_url(string $page, string $key): string
{
    return url($page . ($key === 'checkout' ? '?redirect=checkout' : ''));
}

function set_after_auth_target(string $key): void
{
    if ($key === 'checkout') {
        $_SESSION['after_auth'] = 'checkout.php';
    }
}

/** وجهة ما بعد نجاح الدخول/التسجيل: إتمام الطلب إن وُجدت نية، وإلا صفحة العميل */
function take_after_auth_target(): string
{
    $target = !empty($_SESSION['after_auth']) && $_SESSION['after_auth'] === 'checkout.php'
        ? url('checkout.php')
        : url('account.php');
    unset($_SESSION['after_auth']);
    return $target;
}

/**
 * بوابة إتمام الطلب: يشترط تسجيل الدخول قبل عرض خطوة الدفع.
 * السلة تبقى في الجلسة، وتُحفظ بيانات النموذج غير الحساسة فقط
 * (لا تُحفظ بيانات البطاقة إطلاقاً ولا تُلمس إعدادات بوابة الدفع).
 */
function require_checkout_customer(): void
{
    if (!is_logged_in()) {
        $allowed = ['name', 'phone', 'email', 'city', 'address', 'notes', 'payment_method'];
        $saved = [];
        foreach ($allowed as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field])) {
                $saved[$field] = trim($_POST[$field]);
            }
        }
        if ($saved) {
            $_SESSION['checkout_form'] = $saved;
        }
        $_SESSION['after_auth'] = 'checkout.php';

        flash('info', 'سجّلي الدخول أو أنشئي حساباً لإتمام طلبك — ستبقى قطعك في السلة.');
        redirect(url('login.php?redirect=checkout'));
    }
}

/* ============================================================
 *  التصنيفات والمنتجات
 * ============================================================ */

function all_categories(): array
{
    static $categories = null;
    if ($categories === null) {
        $categories = db()->query('SELECT * FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
    }
    return $categories;
}

function find_product(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id = ? AND p.status = "active"
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function product_image(?string $image): string
{
    $image = trim((string) $image);
    if ($image === '') {
        return url('assets/images/placeholder.svg');
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return url('assets/images/products/' . basename($image));
}

function discount_percent(array $product): int
{
    $price = (float) $product['price'];
    $old = (float) ($product['old_price'] ?? 0);
    if ($old > $price && $old > 0) {
        return (int) round((($old - $price) / $old) * 100);
    }
    return 0;
}

/* ============================================================
 *  صورة الواجهة (قابلة للرفع من لوحة التحكم)
 * ============================================================ */

function hero_image(): string
{
    $image = trim(setting('hero_image', ''));
    if ($image === '') {
        return url('assets/images/hero.svg');
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return url('assets/images/uploads/' . basename($image));
}

/** صورة صفحات الدخول وإنشاء الحساب (مستقلة عن صورة الواجهة الرئيسية) */
function auth_image(): string
{
    $image = trim(setting('auth_image', ''));
    if ($image === '') {
        return url('assets/images/products/p2.svg');
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return url('assets/images/uploads/' . basename($image));
}

/** أيقونة الموقع (تظهر في تبويب المتصفح ونتائج البحث) */
function site_icon(): string
{
    $image = trim(setting('favicon', ''));
    if ($image === '') {
        return url('assets/images/favicon.svg');
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return url('assets/images/uploads/' . basename($image));
}

/** أيقونة حقل البحث (قابلة للاستبدال من لوحة التحكم) */
function search_icon_html(): string
{
    $image = trim(setting('search_icon', ''));
    if ($image !== '') {
        $src = preg_match('#^https?://#i', $image)
            ? $image
            : url('assets/images/uploads/' . basename($image));
        return '<img class="search-icon" src="' . e($src) . '" alt="" aria-hidden="true">';
    }
    return '<svg class="search-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
}

function upload_site_image(array $file, ?string $existing = null, string $prefix = 'hero'): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'image' => $existing];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'image' => $existing, 'error' => 'حدث خطأ أثناء رفع الصورة، حاولي مرة أخرى.'];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'image' => $existing, 'error' => 'حجم الصورة يجب أن يكون أقل من 5 ميغابايت.'];
    }

    $targetDir = ROOT_PATH . '/assets/images/uploads';
    $tmp = (string) ($file['tmp_name'] ?? '');
    $info = @getimagesize($tmp);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];

    if ($info !== false && isset($allowed[$info[2]])) {
        $ext = $allowed[$info[2]];
    } else {
        // السماح بصور SVG مع فحص بسيط للمحتوى
        $raw = @file_get_contents($tmp);
        $originalName = strtolower((string) ($file['name'] ?? ''));
        $mime = (string) ($file['type'] ?? '');
        $isSvg = str_ends_with($originalName, '.svg') || strpos($mime, 'svg') !== false;
        if (!$isSvg || $raw === false || stripos($raw, '<svg') === false
            || preg_match('/<script|onload\s*=|onerror\s*=|javascript:/i', $raw)) {
            return ['ok' => false, 'image' => $existing, 'error' => 'الملف المرفوع ليس صورة صالحة (JPG, PNG, WEBP, GIF, SVG).'];
        }
        $ext = 'svg';
    }

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'image' => $existing, 'error' => 'تعذر إنشاء مجلد الصور.'];
    }

    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $destination = $targetDir . '/' . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        return ['ok' => false, 'image' => $existing, 'error' => 'تعذر حفظ الصورة على السيرفر.'];
    }

    if ($existing && $existing !== '' && !preg_match('#^https?://#i', $existing)) {
        $oldPath = $targetDir . '/' . basename($existing);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return ['ok' => true, 'image' => $filename];
}

/* ============================================================
 *  المقاسات
 * ============================================================ */

function all_sizes(): array
{
    static $sizes = null;
    if ($sizes === null) {
        try {
            $sizes = db()->query('SELECT * FROM sizes ORDER BY sort_order ASC, id ASC')->fetchAll();
        } catch (Throwable $e) {
            $sizes = [];
        }
    }
    return $sizes;
}

function size_map(): array
{
    $map = [];
    foreach (all_sizes() as $size) {
        $map[(int) $size['id']] = $size['name'];
    }
    return $map;
}

function product_size_ids(int $productId): array
{
    if ($productId < 1) {
        return [];
    }
    try {
        $stmt = db()->prepare('SELECT size_id FROM product_sizes WHERE product_id = ? ORDER BY size_id ASC');
        $stmt->execute([$productId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return [];
    }
}

function product_sizes(int $productId): array
{
    if ($productId < 1) {
        return [];
    }
    try {
        $stmt = db()->prepare(
            'SELECT s.id, s.name FROM sizes s
             INNER JOIN product_sizes ps ON ps.size_id = s.id
             WHERE ps.product_id = ? ORDER BY s.sort_order ASC, s.id ASC'
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/* ============================================================
 *  معرض صور المنتج
 * ============================================================ */

function product_gallery(int $productId): array
{
    if ($productId < 1) {
        return [];
    }
    try {
        $stmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** كل صور المنتج: الصورة الأساسية أولاً ثم صور المعرض */
function product_all_images(array $product): array
{
    $images = [product_image($product['image'] ?? null)];
    foreach (product_gallery((int) ($product['id'] ?? 0)) as $row) {
        $images[] = product_image($row['image']);
    }
    return array_values(array_unique($images));
}

/** تحويل $_FILES الخاص برفع عدة ملفات إلى مصفوفة ملفات مفردة */
function normalize_multiple_files(array $file): array
{
    if (!isset($file['name']) || !is_array($file['name'])) {
        return [];
    }
    $out = [];
    foreach ($file['name'] as $index => $name) {
        $out[] = [
            'name'     => (string) $name,
            'type'     => (string) ($file['type'][$index] ?? ''),
            'tmp_name' => (string) ($file['tmp_name'][$index] ?? ''),
            'error'    => (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($file['size'][$index] ?? 0),
        ];
    }
    return $out;
}

/* ============================================================
 *  السلة (تعتمد على الجلسة)
 * ============================================================ */

function cart_raw(): array
{
    return isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
}

/** مفتاح السلة: رقم المنتج:رقم المقاس (0 عند عدم وجود مقاس) */
function cart_key(int $productId, int $sizeId = 0): string
{
    return $productId . ':' . $sizeId;
}

/** @return array{0:int,1:int} */
function parse_cart_key($key): array
{
    $parts = explode(':', (string) $key, 2);
    return [
        max(0, (int) ($parts[0] ?? 0)),
        max(0, (int) ($parts[1] ?? 0)),
    ];
}

function cart_count(): int
{
    $count = 0;
    foreach (cart_raw() as $qty) {
        $count += (int) $qty;
    }
    return $count;
}

function cart_add(int $productId, int $quantity = 1, int $sizeId = 0): array
{
    $quantity = max(1, $quantity);
    $product = find_product($productId);

    if (!$product) {
        return ['ok' => false, 'message' => 'هذا المنتج غير متوفر حالياً.'];
    }
    if ((int) $product['stock'] < 1) {
        return ['ok' => false, 'message' => 'نفد مخزون هذا المنتج.'];
    }

    $available = product_size_ids($productId);
    if ($sizeId > 0 && !in_array($sizeId, $available, true)) {
        return ['ok' => false, 'message' => 'هذا المقاس غير متاح لهذه القطعة.'];
    }
    if ($sizeId === 0 && $available) {
        $sizeId = $available[0];
    }

    $key = cart_key($productId, $sizeId);
    $current = (int) (cart_raw()[$key] ?? 0);
    $newQty = min($current + $quantity, (int) $product['stock']);
    $_SESSION['cart'][$key] = $newQty;

    $sizeName = size_map()[$sizeId] ?? '';
    $message = 'تمت إضافة «' . $product['name'] . '» إلى السلة';
    if ($sizeName !== '') {
        $message .= ' — المقاس ' . $sizeName;
    }

    return [
        'ok'      => true,
        'message' => $message,
        'count'   => cart_count(),
    ];
}

function cart_set(string $key, int $quantity): void
{
    [$productId, $sizeId] = parse_cart_key($key);
    $product = find_product($productId);

    if (!$product) {
        cart_remove($key);
        return;
    }
    $available = product_size_ids($productId);
    if ($sizeId > 0 && !in_array($sizeId, $available, true)) {
        cart_remove($key);
        return;
    }
    $quantity = (int) $quantity;
    if ($quantity < 1) {
        cart_remove($key);
        return;
    }
    $_SESSION['cart'][$key] = min($quantity, max(1, (int) $product['stock']));
}

function cart_remove(string $key): void
{
    unset($_SESSION['cart'][$key]);
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

function cart_summary(): array
{
    $raw = cart_raw();
    $items = [];
    $subtotal = 0.0;
    $count = 0;

    if ($raw) {
        $productIds = [];
        foreach (array_keys($raw) as $key) {
            [$pid] = parse_cart_key($key);
            if ($pid > 0) {
                $productIds[$pid] = $pid;
            }
        }

        if ($productIds) {
            $ids = array_values($productIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare(
                "SELECT p.*, c.name AS category_name
                 FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id IN ($placeholders) AND p.status = 'active'"
            );
            $stmt->execute($ids);

            $products = [];
            foreach ($stmt->fetchAll() as $product) {
                $products[(int) $product['id']] = $product;
            }

            $sizes = size_map();
            foreach ($raw as $key => $qty) {
                [$pid, $sid] = parse_cart_key($key);
                if (!isset($products[$pid])) {
                    continue;
                }
                $product = $products[$pid];
                $quantity = (int) $qty;
                if ($quantity < 1 || (int) $product['stock'] < 1) {
                    continue;
                }
                $quantity = min($quantity, (int) $product['stock']);
                $product['quantity'] = $quantity;
                $product['line_total'] = $quantity * (float) $product['price'];
                $product['cart_key'] = (string) $key;
                $product['size_id'] = $sid;
                $product['size_name'] = $sizes[$sid] ?? '';
                $items[] = $product;
                $subtotal += $product['line_total'];
                $count += $quantity;
            }
        }
    }

    $freeThreshold = (float) setting('free_shipping_threshold', '500');
    $shippingCost = (float) setting('shipping_cost', '30');
    $shipping = 0.0;

    if ($subtotal > 0) {
        $shipping = ($freeThreshold <= 0 || $subtotal >= $freeThreshold) ? 0.0 : $shippingCost;
    }

    return [
        'items'          => $items,
        'subtotal'       => $subtotal,
        'shipping'       => $shipping,
        'total'          => $subtotal + $shipping,
        'count'          => $count,
        'free_threshold' => $freeThreshold,
        'shipping_cost'  => $shippingCost,
    ];
}

/* ============================================================
 *  حالات الطلبات
 * ============================================================ */

function order_statuses(): array
{
    return [
        'pending'   => ['label' => 'قيد الانتظار', 'class' => 'warning'],
        'confirmed' => ['label' => 'مؤكد',        'class' => 'info'],
        'shipped'   => ['label' => 'تم الشحن',    'class' => 'primary'],
        'delivered' => ['label' => 'تم التسليم',  'class' => 'success'],
        'cancelled' => ['label' => 'ملغي',        'class' => 'danger'],
    ];
}

function status_badge(string $status): string
{
    $statuses = order_statuses();
    $info = $statuses[$status] ?? ['label' => $status, 'class' => 'secondary'];
    return '<span class="badge badge-' . e($info['class']) . '">' . e($info['label']) . '</span>';
}

function payment_methods(): array
{
    return [
        'cod'  => [
            'label' => 'الدفع عند الاستلام',
            'desc'  => 'لا حاجة لبطاقة بنكية — تدفعين للموصّل عند وصول الطلب.',
            'icon'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 10h.01M18 14h.01"/></svg>',
            'card'  => false,
        ],
        'mada' => [
            'label' => 'مدى',
            'desc'  => 'الدفع ببطاقة مدى البنكية (تجريبي في هذه النسخة).',
            'icon'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 9.5h19M6 15h5" stroke-linecap="round"/><path d="M15.5 13.5h3.2M15.5 15.2h2" stroke-linecap="round"/></svg>',
            'card'  => true,
        ],
        'visa' => [
            'label' => 'فيزا',
            'desc'  => 'Visa / بطاقة ائتمان (تجريبي في هذه النسخة).',
            'icon'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="m6.5 15 1.4-4 1.4 4M9 15h2M12 11v4h1.8M16 15h2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'card'  => true,
        ],
    ];
}

function enabled_payment_methods(): array
{
    $raw = setting('payment_methods', 'cod,mada,visa');
    $enabled = array_filter(array_map('trim', explode(',', (string) $raw)));
    $all = payment_methods();
    $out = [];
    foreach ($all as $key => $info) {
        if (in_array($key, $enabled, true)) {
            $out[$key] = $info;
        }
    }
    // ضمان وجود طريقة دفع واحدة على الأقل
    return $out ?: ['cod' => $all['cod']];
}

function payment_label(string $method): string
{
    $all = payment_methods();
    return isset($all[$method]) ? e($all[$method]['label']) : e($method);
}

/** هل تحتاج طريقة الدفع إلى إدخال بيانات بطاقة؟ */
function payment_method_requires_card(string $method): bool
{
    $all = payment_methods();
    return !empty($all[$method]['card']);
}

/** خوارزمية لوهن للتحقق من صيغة رقم البطاقة (تجريبي فقط) */
function luhn_valid(string $number): bool
{
    $sum = 0;
    $alternate = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = (int) $number[$i];
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $alternate = !$alternate;
    }
    return $sum % 10 === 0;
}

/* ============================================================
 *  رفع الصور
 * ============================================================ */

function upload_product_image(array $file, ?string $existing = null): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'image' => $existing];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'image' => $existing, 'error' => 'حدث خطأ أثناء رفع الصورة، حاول مرة أخرى.'];
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'image' => $existing, 'error' => 'حجم الصورة يجب أن يكون أقل من 5 ميغابايت.'];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'image' => $existing, 'error' => 'الملف المرفوع ليس صورة صالحة.'];
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF  => 'gif',
    ];
    if (!isset($allowed[$info[2]])) {
        return ['ok' => false, 'image' => $existing, 'error' => 'صيغة الصورة غير مدعومة (JPG, PNG, WEBP, GIF).'];
    }

    if (!is_dir(UPLOAD_PATH) && !@mkdir(UPLOAD_PATH, 0775, true) && !is_dir(UPLOAD_PATH)) {
        return ['ok' => false, 'image' => $existing, 'error' => 'تعذر إنشاء مجلد الصور.'];
    }

    $filename = 'p_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $allowed[$info[2]];
    $destination = UPLOAD_PATH . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'image' => $existing, 'error' => 'تعذر حفظ الصورة على السيرفر.'];
    }

    return ['ok' => true, 'image' => $filename];
}

/* ============================================================
 *  أدوات مساعدة إضافية
 * ============================================================ */

function pagination_links(int $page, int $pages, array $params = []): string
{
    if ($pages <= 1) {
        return '';
    }

    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $html = '<nav class="pagination" aria-label="تصفح الصفحات">';

    $build = function (int $target) use ($script, $params): string {
        $query = http_build_query(array_merge($params, ['page' => $target]));
        return e($script . '?' . $query);
    };

    if ($page > 1) {
        $html .= '<a class="page-link" href="' . $build($page - 1) . '" aria-label="السابق">&rsaquo;</a>';
    }

    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1) {
        $html .= '<a class="page-link" href="' . $build(1) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-dots">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $page
            ? '<span class="page-link is-active">' . $i . '</span>'
            : '<a class="page-link" href="' . $build($i) . '">' . $i . '</a>';
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<span class="page-dots">…</span>';
        }
        $html .= '<a class="page-link" href="' . $build($pages) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        $html .= '<a class="page-link" href="' . $build($page + 1) . '" aria-label="التالي">&lsaquo;</a>';
    }

    return $html . '</nav>';
}

function order_number_for(int $id): string
{
    return 'MRK-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}
