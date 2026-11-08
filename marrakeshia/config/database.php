<?php
/**
 * Marrakeshia — إعدادات الاتصال بقاعدة البيانات والثوابت العامة
 * متجر مراكشية | PHP + MySQL
 */

date_default_timezone_set('Africa/Casablanca');

/* ------- بيانات الاتصال (عدّلها حسب إعدادات XAMPP لديك) ------- */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'marrakeshia');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ------- مسارات المشروع ------- */
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/images/products');

/* ------- الحساب التلقائي لرابط المشروع ------- */
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$appRoot = str_replace('\\', '/', (string) realpath(ROOT_PATH));
$basePath = '';
if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
    $basePath = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
}
define('BASE_URL', rtrim($basePath, '/'));

/* ------- الجلسة ------- */
if (session_status() === PHP_SESSION_NONE) {
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

/**
 * الاتصال بقاعدة البيانات (اتصال واحد يُعاد استخدامه)
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
            . '<title>خطأ في الاتصال</title></head>'
            . '<body style="font-family:Tahoma,Arial,sans-serif;background:#faf6f1;color:#2b2118;padding:40px;text-align:center">'
            . '<div style="max-width:640px;margin:40px auto;background:#fff;border-radius:18px;padding:32px;box-shadow:0 12px 40px rgba(0,0,0,.08)">'
            . '<h1 style="color:#b5442e;margin-top:0">تعذر الاتصال بقاعدة البيانات</h1>'
            . '<p>تأكد من تشغيل <strong>MySQL</strong> في لوحة تحكم XAMPP، ثم استورد ملف '
            . '<code>database/marrakeshia.sql</code> أو افتح '
            . '<a href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/install.php">install.php</a> لإعداد القاعدة تلقائياً.</p>'
            . '<p style="color:#7a6a5d;font-size:14px">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div></body></html>';
        exit;
    }

    return $pdo;
}
