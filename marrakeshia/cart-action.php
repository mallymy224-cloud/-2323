<?php
require_once __DIR__ . '/includes/functions.php';

$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
    || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);

if (!is_post()) {
    redirect(url('cart.php'));
}

verify_csrf($wantsJson);

$action = (string) ($_POST['action'] ?? 'add');
$count = cart_count();
$message = '';
$ok = true;

switch ($action) {
    case 'add':
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $sizeId = (int) ($_POST['size_id'] ?? 0);
        $result = cart_add($productId, $quantity, $sizeId);
        $ok = $result['ok'];
        $message = $result['message'];
        $count = cart_count();
        break;

    case 'update':
        $quantities = $_POST['quantity'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $key => $qty) {
                cart_set((string) $key, (int) $qty);
            }
        }
        $message = 'تم تحديث السلة بنجاح.';
        $count = cart_count();
        break;

    case 'remove':
        $key = (string) ($_POST['remove'] ?? $_POST['product_id'] ?? '');
        if (ctype_digit($key)) {
            $key = cart_key((int) $key, 0);
        }
        cart_remove($key);
        $message = 'تم حذف القطعة من السلة.';
        $count = cart_count();
        break;

    case 'clear':
        cart_clear();
        $message = 'تم تفريغ السلة.';
        $count = 0;
        break;

    default:
        $ok = false;
        $message = 'طلب غير صالح.';
}

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => $ok,
        'message' => $message,
        'count'   => $count,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

flash($ok ? 'success' : 'error', $message);

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$back = url('cart.php');
if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $host) {
    $back = $referer;
}
redirect($back);
