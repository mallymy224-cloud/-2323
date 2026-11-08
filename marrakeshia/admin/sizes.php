<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$editId = (int) ($_GET['edit'] ?? 0);
$form = ['name' => '', 'sort_order' => '0'];
$errors = [];

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM sizes WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $size = $stmt->fetch();
    if ($size) {
        $form = ['name' => $size['name'], 'sort_order' => (string) $size['sort_order']];
    } else {
        flash('error', 'لم يتم العثور على المقاس.');
        redirect(url('admin/sizes.php'));
    }
}

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $pdo->prepare('DELETE FROM sizes WHERE id = ?')->execute([$deleteId]);
            flash('success', 'تم حذف المقاس وإزالته من المنتجات المرتبطة به.');
        }
        redirect(url('admin/sizes.php'));
    }

    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['sort_order'] = trim((string) ($_POST['sort_order'] ?? '0'));

    if (mb_strlen($form['name']) < 1 || mb_strlen($form['name']) > 40) {
        $errors[] = 'اسم المقاس مطلوب وألا يتجاوز 40 حرفاً (مثال: S أو مقاس واحد).';
    }
    if ($form['sort_order'] === '' || !preg_match('/^-?\d+$/', $form['sort_order'])) {
        $form['sort_order'] = '0';
    }

    $duplicate = $pdo->prepare('SELECT id FROM sizes WHERE name = ? AND id <> ? LIMIT 1');
    $duplicate->execute([$form['name'], $editId]);
    if ($duplicate->fetchColumn()) {
        $errors[] = 'هذا المقاس موجود مسبقاً.';
    }

    if (!$errors) {
        if ($editId > 0) {
            $pdo->prepare('UPDATE sizes SET name = ?, sort_order = ? WHERE id = ?')
                ->execute([$form['name'], (int) $form['sort_order'], $editId]);
            flash('success', 'تم تحديث المقاس بنجاح.');
        } else {
            $pdo->prepare('INSERT INTO sizes (name, sort_order) VALUES (?, ?)')
                ->execute([$form['name'], (int) $form['sort_order']]);
            flash('success', 'تمت إضافة المقاس بنجاح.');
        }
        redirect(url('admin/sizes.php'));
    }
}

$sizes = $pdo->query(
    'SELECT s.*, COUNT(ps.product_id) AS products_count
     FROM sizes s LEFT JOIN product_sizes ps ON ps.size_id = s.id
     GROUP BY s.id
     ORDER BY s.sort_order ASC, s.id ASC'
)->fetchAll();

$pageTitle = 'المقاسات';
$pageSubtitle = 'أضيفي المقاسات المتاحة واربطيها بالمنتجات';
$activePage = 'sizes';

require __DIR__ . '/includes/header.php';
?>

<div class="admin-grid-2 admin-grid-categories">
    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2><?= $editId > 0 ? 'تعديل المقاس' : 'إضافة مقاس' ?></h2>
                <p><?= $editId > 0 ? 'حدّثي اسم المقاس وترتيبه' : 'مثال: XS / S / M / L / XL / مقاس واحد' ?></p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="admin-alert admin-alert-danger">
                <strong>يرجى تصحيح ما يلي:</strong>
                <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/sizes.php' . ($editId > 0 ? '?edit=' . $editId : '')) ?>">
            <?= csrf_field() ?>
            <div class="admin-field-grid">
                <label class="admin-field admin-field-full">
                    <span>اسم المقاس <b>*</b></span>
                    <input type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="40" placeholder="مثال: XL أو مقاس واحد">
                    <small class="admin-field-help">سيظهر كما هو للعميلة في صفحة المنتج.</small>
                </label>

                <label class="admin-field">
                    <span>ترتيب العرض</span>
                    <input type="number" name="sort_order" value="<?= e($form['sort_order']) ?>" step="1">
                    <small class="admin-field-help">الأرقام الأصغر تظهر أولاً.</small>
                </label>
            </div>

            <div class="admin-form-actions">
                <button class="admin-btn admin-btn-primary" type="submit"><?= $editId > 0 ? 'حفظ التعديلات' : 'إضافة المقاس' ?></button>
                <?php if ($editId > 0): ?>
                    <a class="admin-btn admin-btn-ghost" href="<?= url('admin/sizes.php') ?>">إلغاء</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="admin-size-hint">
            <strong>كيف أستخدم المقاسات؟</strong>
            <p>بعد إضافة المقاسات، افتحي <a href="<?= url('admin/products.php') ?>">صفحة المنتجات</a> ثم عدّلي أي منتج وحدّدي المقاسات المتاحة له من قسم «المقاسات المتوفرة».</p>
        </div>
    </section>

    <section class="admin-panel admin-panel-flush">
        <div class="admin-panel-head">
            <div><h2>كل المقاسات</h2><p><?= count($sizes) ?> مقاس</p></div>
        </div>

        <?php if ($sizes): ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>المقاس</th><th>المنتجات</th><th>الترتيب</th><th class="admin-th-actions">إجراءات</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizes as $item): ?>
                            <tr>
                                <td><span class="admin-size-chip"><?= e($item['name']) ?></span></td>
                                <td><span class="admin-stock-pill is-ok"><?= (int) $item['products_count'] ?> منتج</span></td>
                                <td><?= (int) $item['sort_order'] ?></td>
                                <td>
                                    <div class="admin-row-actions">
                                        <a class="admin-icon-btn" href="<?= url('admin/sizes.php?edit=' . (int) $item['id']) ?>" title="تعديل">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z"/></svg>
                                        </a>
                                        <form method="post" action="<?= url('admin/sizes.php') ?>" class="admin-inline-form" data-confirm="حذف هذا المقاس؟ سيُزال من كل المنتجات المرتبطة به.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <button class="admin-icon-btn is-danger" type="submit" title="حذف">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty"><p>لا توجد مقاسات بعد. أضيفي أول مقاس من النموذج.</p></div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
