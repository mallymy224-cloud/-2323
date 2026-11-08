<?php
/**
 * Marrakeshia — معالج تثبيت قاعدة البيانات
 * افتح الرابط: http://localhost/marrakeshia/install.php
 *
 * ملاحظة: بعد إتمام التثبيت احذف هذا الملف من السيرفر.
 */

require_once __DIR__ . '/includes/functions.php';

$errors = [];
$success = false;
$upgraded = false;
$installed = false;
$tablesCount = 0;
$adminEmail = 'admin@marrakeshia.com';
$adminPassword = 'admin123';
$force = is_post() && isset($_POST['force']);
$upgrade = is_post() && isset($_POST['upgrade']);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * تحديث بنية قاعدة البيانات دون حذف البيانات الحالية
 * (يضيف جداول المقاسات ومعرض الصور وعمود المقاس وإعدادات الصور والأيقونات وطرق الدفع)
 */
function upgrade_schema(PDO $pdo): array
{
    $errors = [];

    try {
        $pdo->exec('USE `' . DB_NAME . '`');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `sizes` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `name` VARCHAR(40) NOT NULL,
              `sort_order` INT NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_sizes_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `product_sizes` (
              `product_id` INT UNSIGNED NOT NULL,
              `size_id` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`product_id`, `size_id`),
              CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`)
                REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_ps_size` FOREIGN KEY (`size_id`)
                REFERENCES `sizes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `product_images` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `product_id` INT UNSIGNED NOT NULL,
              `image` VARCHAR(255) NOT NULL,
              `sort_order` INT NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_pi_product` (`product_id`),
              CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`)
                REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $hasSizeColumn = $pdo->query("SHOW COLUMNS FROM `order_items` LIKE 'size'")->fetch();
        if (!$hasSizeColumn) {
            $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `size` VARCHAR(40) DEFAULT NULL AFTER `quantity`");
        }

        $pdo->exec(
            "INSERT IGNORE INTO `sizes` (`id`, `name`, `sort_order`) VALUES
             (1, 'XS', 1), (2, 'S', 2), (3, 'M', 3), (4, 'L', 4),
             (5, 'XL', 5), (6, 'XXL', 6), (7, 'مقاس واحد', 7)"
        );

        $pdo->exec(
            "INSERT IGNORE INTO `product_sizes` (`product_id`, `size_id`) VALUES
             (1,2),(1,3),(1,4),(1,5),
             (2,2),(2,3),(2,4),(2,5),(2,6),
             (3,1),(3,2),(3,3),(3,4),
             (4,2),(4,3),(4,4),
             (5,2),(5,3),(5,4),(5,5),
             (6,2),(6,3),(6,4),(6,5),
             (7,1),(7,2),(7,3),(7,4),
             (8,7),(9,7),
             (10,2),(10,3),(10,4),
             (11,2),(11,3),(11,4),(11,5),
             (12,2),(12,3),(12,4),(12,5)"
        );

        $pdo->exec(
            "INSERT IGNORE INTO `product_images` (`id`, `product_id`, `image`, `sort_order`) VALUES
             (1,1,'detail-1.svg',1),(2,1,'detail-2.svg',2),
             (3,2,'detail-3.svg',1),
             (4,3,'detail-1.svg',1),(5,3,'detail-2.svg',2),(6,3,'detail-3.svg',3),
             (7,5,'detail-2.svg',1),
             (8,10,'detail-1.svg',1),(9,10,'detail-3.svg',2)"
        );

        $pdo->exec(
            "INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
             ('hero_image', ''), ('auth_image', ''), ('favicon', ''),
             ('search_icon', ''), ('payment_methods', 'cod,mada,visa')"
        );
    } catch (PDOException $e) {
        $errors[] = 'تعذر تحديث البنية: ' . $e->getMessage();
    }

    return $errors;
}

/* ---------- الاتصال بخادم MySQL ---------- */
try {
    $installer = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    $installer = null;
    $errors[] = 'تعذر الاتصال بخادم MySQL. تأكد من تشغيل MySQL في XAMPP. التفاصيل: ' . $e->getMessage();
}

/* ---------- هل القاعدة مثبتة مسبقاً؟ ---------- */
if ($installer instanceof PDO) {
    try {
        $stmt = $installer->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([DB_NAME]);

        if ($stmt->fetchColumn() !== false) {
            $stmt = $installer->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?');
            $stmt->execute([DB_NAME]);
            $tablesCount = (int) $stmt->fetchColumn();
            $installed = $tablesCount > 0;
        }
    } catch (PDOException $e) {
        $errors[] = 'تعذر فحص قاعدة البيانات: ' . $e->getMessage();
    }
}

/* ---------- تنفيذ التثبيت / التحديث ---------- */
if (is_post() && !$errors) {
    if ($upgrade) {
        $upgradeErrors = upgrade_schema($installer);
        if ($upgradeErrors) {
            $errors = array_merge($errors, $upgradeErrors);
        } else {
            $upgraded = true;
            $success = true;
            $installed = true;
        }
    } elseif ($installed && !$force) {
        $errors[] = 'قاعدة البيانات مثبتة مسبقاً. إن أردت إعادة التثبيت فكل البيانات الحالية ستُحذف، لذلك استخدم زر إعادة التثبيت أسفل الصفحة.';
    } else {
        $sqlFile = __DIR__ . '/database/marrakeshia.sql';

        if (!is_file($sqlFile)) {
            $errors[] = 'لم يتم العثور على ملف قاعدة البيانات: database/marrakeshia.sql';
        } else {
            $sql = file_get_contents($sqlFile);

            if ($sql === false || trim($sql) === '') {
                $errors[] = 'ملف قاعدة البيانات فارغ أو غير قابل للقراءة.';
            } else {
                // حذف أسطر التعليقات
                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

                try {
                    $installer->exec('SET NAMES utf8mb4');
                    foreach ($statements as $index => $statement) {
                        $statement = trim($statement);
                        if ($statement === '') {
                            continue;
                        }
                        try {
                            $installer->exec($statement);
                        } catch (PDOException $e) {
                            $errors[] = 'خطأ في العبارة رقم ' . ($index + 1) . ' أثناء الاستيراد: ' . $e->getMessage();
                            break;
                        }
                    }
                } catch (PDOException $e) {
                    $errors[] = 'تعذر بدء الاستيراد: ' . $e->getMessage();
                }

                if (!$errors) {
                    // إعادة توليد كلمة مرور المدير الحقيقية (bcrypt) لضمان توافقها مع السيرفر
                    try {
                        $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
                        $stmt = $installer->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
                        $stmt->execute([$hash, $adminEmail]);

                        if ($stmt->rowCount() === 0) {
                            $stmt = $installer->prepare(
                                "INSERT INTO users (name, email, phone, password, address, city, role)
                                 VALUES ('مدير المتجر', ?, '0600000000', ?, 'مراكش، المغرب', 'مراكش', 'admin')"
                            );
                            $stmt->execute([$adminEmail, $hash]);
                        }

                        $success = true;
                        $installed = true;
                    } catch (PDOException $e) {
                        $errors[] = 'تم استيراد الجداول لكن تعذر ضبط حساب المدير: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$storeUrl = url('index.php');
$adminUrl = url('admin/login.php');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تثبيت قاعدة البيانات | مراكشية</title>
    <link rel="icon" type="image/svg+xml" href="<?= h(url('assets/images/favicon.svg')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #2b2118;
            --muted: #7a6a5d;
            --cream: #faf6f1;
            --line: #e8dccf;
            --clay: #b5442e;
            --green: #3f7d58;
            --danger: #b5442e;
            --gold: #c19a3f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            font-family: "Cairo", "Segoe UI", Tahoma, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 15% 10%, rgba(193, 154, 63, .16), transparent 45%),
                radial-gradient(circle at 88% 90%, rgba(181, 68, 46, .12), transparent 42%),
                var(--cream);
        }
        .card {
            width: min(760px, 100%);
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(43, 33, 24, .12);
            overflow: hidden;
        }
        .card-head {
            padding: 30px 34px;
            background: var(--ink);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .mark {
            width: 52px; height: 52px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
            color: #e8c979;
            flex-shrink: 0;
        }
        .card-head h1 { margin: 0; font-size: 24px; }
        .card-head p { margin: 4px 0 0; color: #cbb9a7; font-size: 13px; }
        .card-body { padding: 30px 34px 34px; }
        .panel {
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 18px;
            background: #fdfbf8;
        }
        .panel h2 { margin: 0 0 10px; font-size: 16px; }
        .panel p, .panel li { font-size: 14px; line-height: 1.9; color: var(--muted); }
        .panel ul { margin: 8px 0 0; padding-inline-start: 20px; }
        .alert {
            border-radius: 14px;
            padding: 16px 18px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.9;
        }
        .alert-error { background: #fdf1ef; border: 1px solid #f0cdc8; color: #8d3122; }
        .alert-success { background: #eef7f1; border: 1px solid #cbe5d6; color: #256040; }
        .alert-warning { background: #fdf7e8; border: 1px solid #f0e0b5; color: #7d6417; }
        .alert ul { margin: 8px 0 0; padding-inline-start: 20px; }
        .alert code {
            background: rgba(0,0,0,.06);
            border-radius: 6px;
            padding: 1px 6px;
            font-size: 12px;
            direction: ltr;
            display: inline-block;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .stat {
            border: 1px dashed var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 13px;
            color: var(--muted);
            direction: ltr;
            text-align: left;
        }
        .stat strong { display: block; color: var(--ink); font-size: 14px; margin-top: 3px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border: 0;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: var(--clay); color: #fff; box-shadow: 0 8px 20px rgba(181,68,46,.24); }
        .btn-dark { background: var(--ink); color: #fff; }
        .btn-outline { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .btn-danger { background: #fff; color: var(--danger); border: 1px solid #f0cdc8; }
        .note {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px dashed var(--line);
            font-size: 12px;
            color: var(--muted);
            line-height: 1.9;
        }
        .note strong { color: var(--clay); }
        .kv { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--line); font-size: 13px; }
        .kv:last-child { border-bottom: 0; }
        .kv span { color: var(--muted); }
        .kv b { direction: ltr; }
        @media (max-width: 560px) {
            .card-head, .card-body { padding: 22px; }
            .card-head h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-head">
        <span class="mark" aria-hidden="true">
            <svg viewBox="0 0 48 48" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M24 4c7 7 12 14 12 22a12 12 0 0 1-24 0C12 18 17 11 24 4Z"/>
                <path d="M24 14v20M18 24h12"/>
            </svg>
        </span>
        <div>
            <h1>تثبيت قاعدة بيانات مراكشية</h1>
            <p>PHP <?= h(PHP_VERSION) ?> — MySQL / MariaDB</p>
        </div>
    </div>

    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-error">
                <strong>تعذر إتمام التثبيت:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php if ($upgraded): ?>
                    <strong>تم تحديث قاعدة البيانات بنجاح.</strong>
                    تمت إضافة جداول المقاسات وعمود المقاس وإعدادات صورة الواجهة وطرق الدفع دون حذف بياناتك الحالية.
                <?php else: ?>
                    <strong>تم التثبيت بنجاح.</strong>
                    تم إنشاء قاعدة البيانات <code><?= h(DB_NAME) ?></code> واستيراد المنتجات والطلبات التجريبية.
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>بيانات الدخول للوحة التحكم</h2>
                <div class="kv"><span>البريد الإلكتروني</span><b><?= h($adminEmail) ?></b></div>
                <div class="kv"><span>كلمة المرور</span><b><?= h($adminPassword) ?></b></div>
                <p style="margin:12px 0 0">يُنصح بتغيير كلمة المرور من صفحة <strong>الإعدادات</strong> بعد أول تسجيل دخول.</p>
            </div>

            <div class="actions">
                <a class="btn btn-primary" href="<?= h($storeUrl) ?>">فتح المتجر</a>
                <a class="btn btn-dark" href="<?= h($adminUrl) ?>">لوحة التحكم</a>
            </div>

            <div class="note">
                <strong>مهم:</strong> احذف الملف <code>install.php</code> من مجلد المشروع بعد التثبيت حتى لا يستطيع أي شخص إعادة تثبيت القاعدة وحذف البيانات.
            </div>
        <?php elseif ($installed && !$force): ?>
            <div class="alert alert-warning">
                <strong>قاعدة البيانات مثبتة مسبقاً.</strong>
                يوجد <?= (int) $tablesCount ?> جدولاً في قاعدة <code><?= h(DB_NAME) ?></code>. يمكنك استخدام المتجر مباشرة،
                أو إعادة التثبيت من الصفر إذا أردت (سيتم حذف البيانات الحالية).
            </div>

            <div class="actions">
                <a class="btn btn-primary" href="<?= h($storeUrl) ?>">فتح المتجر</a>
                <a class="btn btn-dark" href="<?= h($adminUrl) ?>">لوحة التحكم</a>
            </div>

            <div class="grid" style="margin-top:20px">
                <div class="stat">DB_HOST<strong><?= h(DB_HOST) ?></strong></div>
                <div class="stat">DB_NAME<strong><?= h(DB_NAME) ?></strong></div>
                <div class="stat">DB_USER<strong><?= h(DB_USER) ?></strong></div>
            </div>

            <div class="alert alert-warning" style="margin-top:22px">
                <strong>تحديث البنية بدون حذف البيانات:</strong>
                إن كنت ثبّتّ نسخة أقدم وتريد المزايا الجديدة (المقاسات، طرق الدفع، صورة الواجهة)، اضغطي الزر التالي.
            </div>
            <form method="post">
                <input type="hidden" name="upgrade" value="1">
                <div class="actions">
                    <button class="btn btn-primary" type="submit">تحديث قاعدة البيانات للإصدار الجديد</button>
                </div>
            </form>

            <form method="post" onsubmit="return confirm('تحذير: إعادة التثبيت ستحذف كل المنتجات والطلبات والعملاء الحاليين. هل أنت متأكد؟');">
                <input type="hidden" name="force" value="1">
                <div class="actions" style="margin-top:22px">
                    <button class="btn btn-danger" type="submit">إعادة تثبيت قاعدة البيانات (حذف البيانات)</button>
                </div>
            </form>
        <?php else: ?>
            <div class="panel">
                <h2>ما سيقوم به المعالج</h2>
                <ul>
                    <li>إنشاء قاعدة البيانات <code><?= h(DB_NAME) ?></code> بترميز <code>utf8mb4</code>.</li>
                    <li>إنشاء جداول التصنيفات، المنتجات، المقاسات، المستخدمين، الطلبات والإعدادات.</li>
                    <li>استيراد 12 منتجاً و5 تصنيفات و7 مقاسات و3 طلبات تجريبية.</li>
                    <li>تفعيل طرق الدفع: الدفع عند الاستلام، مدى وفيزا.</li>
                    <li>جداول معرض صور المنتجات، وإعدادات صور الواجهة والدخول والأيقونات (قابلة للتغيير من اللوحة).</li>
                    <li>ضبط حساب المدير وكلمة المرور الخاصة به.</li>
                </ul>
            </div>

            <div class="grid">
                <div class="stat">DB_HOST<strong><?= h(DB_HOST) ?></strong></div>
                <div class="stat">DB_NAME<strong><?= h(DB_NAME) ?></strong></div>
                <div class="stat">DB_USER<strong><?= h(DB_USER) ?></strong></div>
            </div>

            <?php if ($installed): ?>
                <div class="alert alert-warning" style="margin-top:18px">
                    يوجد <?= (int) $tablesCount ?> جدولاً حالياً. سيتم حذفها جميعاً قبل الاستيراد.
                </div>
            <?php endif; ?>

            <form method="post">
                <?php if ($installed): ?><input type="hidden" name="force" value="1"><?php endif; ?>
                <div class="actions" style="margin-top:22px">
                    <button class="btn btn-primary" type="submit">
                        <?= $installed ? 'إعادة تثبيت قاعدة البيانات' : 'بدء التثبيت الآن' ?>
                    </button>
                    <a class="btn btn-outline" href="<?= h($storeUrl) ?>">فتح المتجر</a>
                </div>
            </form>

            <div class="note">
                إن لم يعمل المعالج، يمكنك استيراد الملف <code>database/marrakeshia.sql</code> يدوياً من phpMyAdmin،
                ثم تعديل بيانات الاتصال في <code>config/database.php</code> عند الحاجة.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
