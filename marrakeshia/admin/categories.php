<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$editId = (int) ($_GET['edit'] ?? 0);
$form = ['name' => '', 'description' => '', 'sort_order' => '0'];
$currentImage = null;

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $category = $stmt->fetch();
    if ($category) {
        $form = [
            'name'        => $category['name'],
            'description' => (string) $category['description'],
            'sort_order'  => (string) $category['sort_order'],
        ];
        $currentImage = $category['image'];
    } else {
        flash('error', 'لم يتم العثور على التصنيف.');
        redirect(url('admin/categories.php'));
    }
}

$errors = [];

if (is_post()) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);
        if ($deleteId > 0) {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$deleteId]);
            flash('success', 'تم حذف التصنيف. المنتجات المرتبطة به أصبحت بدون تصنيف.');
        }
        redirect(url('admin/categories.php'));
    }

    $form['name']        = trim((string) ($_POST['name'] ?? ''));
    $form['description'] = trim((string) ($_POST['description'] ?? ''));
    $form['sort_order']  = trim((string) ($_POST['sort_order'] ?? '0'));

    if (mb_strlen($form['name']) < 2) {
        $errors[] = 'اسم التصنيف مطلوب (حرفان على الأقل).';
    }
    if (mb_strlen($form['description']) > 255) {
        $errors[] = 'وصف التصنيف يجب ألا يتجاوز 255 حرفاً.';
    }
    if ($form['sort_order'] === '' || !preg_match('/^-?\d+$/', $form['sort_order'])) {
        $form['sort_order'] = '0';
    }

    $upload = upload_product_image($_FILES['image'] ?? [], $currentImage);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }
    $imageName = $upload['image'];

    if (!empty($_POST['remove_image']) && (!isset($_FILES['image']['error']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE)) {
        $imageName = null;
    }

    if (!$errors) {
        if ($editId > 0) {
            $pdo->prepare('UPDATE categories SET name = ?, description = ?, image = ?, sort_order = ? WHERE id = ?')
                ->execute([$form['name'], $form['description'] !== '' ? $form['description'] : null, $imageName, (int) $form['sort_order'], $editId]);
            flash('success', 'تم تحديث التصنيف بنجاح.');
        } else {
            $pdo->prepare('INSERT INTO categories (name, description, image, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$form['name'], $form['description'] !== '' ? $form['description'] : null, $imageName, (int) $form['sort_order']]);
            flash('success', 'تمت إضافة التصنيف بنجاح.');
        }
        redirect(url('admin/categories.php'));
    }
}

$categories = $pdo->query(
    'SELECT c.*, COUNT(p.id) AS products_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.sort_order ASC, c.id ASC'
)->fetchAll();

$pageTitle = 'التصنيفات';
$pageSubtitle = 'نظّمي منتجات المتجر ضمن أقسام واضحة';
$activePage = 'categories';

require __DIR__ . '/includes/header.php';
?>

<div class="admin-grid-2 admin-grid-categories">
    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2><?= $editId > 0 ? 'تعديل التصنيف' : 'إضافة تصنيف' ?></h2>
                <p><?= $editId > 0 ? 'حدّثي بيانات التصنيف' : 'أنشئي قسماً جديداً في المتجر' ?></p>
            </div>
        </div>

        <?php if ($errors): ?>
            <div class="admin-alert admin-alert-danger">
                <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('admin/categories.php' . ($editId > 0 ? '?edit=' . $editId : '')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="admin-field-grid">
                <label class="admin-field admin-field-full">
                    <span>اسم التصنيف <b>*</b></span>
                    <input type="text" name="name" value="<?= e($form['name']) ?>" required placeholder="مثال: قفاطين وعبايات">
                </label>

                <label class="admin-field admin-field-full">
                    <span>وصف قصير</span>
                    <textarea name="description" rows="3" maxlength="255" placeholder="وصف يظهر أعلى صفحة التصنيف"><?= e($form['description']) ?></textarea>
                </label>

                <label class="admin-field">
                    <span>ترتيب العرض</span>
                    <input type="number" name="sort_order" value="<?= e($form['sort_order']) ?>" step="1">
                    <small class="admin-field-help">الأرقام الأصغر تظهر أولاً.</small>
                </label>

                <div class="admin-field admin-field-full">
                    <span>صورة التصنيف</span>
                    <div class="admin-image-preview admin-image-preview-sm" id="imagePreview">
                        <img src="<?= product_image($currentImage) ?>" alt="صورة التصنيف" data-preview-img>
                    </div>
                    <label class="admin-file-field">
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-image-input data-preview-target="#imagePreview">
                        <span>اختيار صورة</span>
                    </label>
                    <?php if ($currentImage): ?>
                        <label class="admin-check admin-check-danger">
                            <input type="checkbox" name="remove_image" value="1">
                            <span><strong>حذف الصورة الحالية</strong></span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-form-actions">
                <button class="admin-btn admin-btn-primary" type="submit"><?= $editId > 0 ? 'حفظ التعديلات' : 'إضافة التصنيف' ?></button>
                <?php if ($editId > 0): ?>
                    <a class="admin-btn admin-btn-ghost" href="<?= url('admin/categories.php') ?>">إلغاء</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="admin-panel admin-panel-flush">
        <div class="admin-panel-head">
            <div><h2>كل التصنيفات</h2><p><?= count($categories) ?> تصنيف</p></div>
        </div>

        <?php if ($categories): ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>التصنيف</th><th>المنتجات</th><th>الترتيب</th><th class="admin-th-actions">إجراءات</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $item): ?>
                            <tr>
                                <td>
                                    <div class="admin-product-cell">
                                        <img src="<?= product_image($item['image']) ?>" alt="<?= e($item['name']) ?>" width="52" height="62" loading="lazy">
                                        <div>
                                            <strong><?= e($item['name']) ?></strong>
                                            <small><?= e(excerpt($item['description'], 60)) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="admin-stock-pill is-ok"><?= (int) $item['products_count'] ?> منتج</span></td>
                                <td><?= (int) $item['sort_order'] ?></td>
                                <td>
                                    <div class="admin-row-actions">
                                        <a class="admin-icon-btn" href="<?= url('admin/categories.php?edit=' . (int) $item['id']) ?>" title="تعديل">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z"/></svg>
                                        </a>
                                        <form method="post" action="<?= url('admin/categories.php') ?>" class="admin-inline-form" data-confirm="حذف هذا التصنيف؟ ستصبح منتجاته بدون تصنيف.">
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
            <div class="admin-empty"><p>لا توجد تصنيفات بعد. أضيفي أول تصنيف من النموذج.</p></div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
