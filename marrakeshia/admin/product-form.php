<?php
require_once __DIR__ . '/includes/auth.php';
$pdo = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$currentImage = null;

$data = [
    'name'              => '',
    'category_id'       => 0,
    'short_description' => '',
    'description'       => '',
    'details'           => '',
    'price'             => '',
    'old_price'         => '',
    'stock'             => '0',
    'status'            => 'active',
    'featured'          => 0,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('error', 'لم يتم العثور على المنتج المطلوب.');
        redirect(url('admin/products.php'));
    }
    $currentImage = $product['image'];
    $data = array_merge($data, $product);
}

$errors = [];
$selectedSizes = $isEdit ? product_size_ids($id) : [];
$gallery = $isEdit ? product_gallery($id) : [];

if (is_post()) {
    verify_csrf();

    $postedSizes = isset($_POST['sizes']) && is_array($_POST['sizes'])
        ? array_map('intval', $_POST['sizes'])
        : [];
    $validSizeIds = array_map(static fn($s) => (int) $s['id'], all_sizes());
    $postedSizes = array_values(array_intersect($postedSizes, $validSizeIds));
    $selectedSizes = $postedSizes;

    // صور المعرض الإضافية
    $removeGallery = isset($_POST['remove_gallery']) && is_array($_POST['remove_gallery'])
        ? array_values(array_filter(array_map('intval', $_POST['remove_gallery'])))
        : [];
    $galleryUploads = [];
    foreach (normalize_multiple_files($_FILES['gallery'] ?? []) as $galleryFile) {
        if (($galleryFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $galleryUpload = upload_product_image($galleryFile, null);
        if (!$galleryUpload['ok']) {
            $errors[] = $galleryUpload['error'];
        } elseif (!empty($galleryUpload['image'])) {
            $galleryUploads[] = $galleryUpload['image'];
        }
    }

    $data['name']              = trim((string) ($_POST['name'] ?? ''));
    $data['category_id']       = (int) ($_POST['category_id'] ?? 0);
    $data['short_description'] = trim((string) ($_POST['short_description'] ?? ''));
    $data['description']       = trim((string) ($_POST['description'] ?? ''));
    $data['details']           = trim((string) ($_POST['details'] ?? ''));
    $data['price']             = trim((string) ($_POST['price'] ?? ''));
    $data['old_price']         = trim((string) ($_POST['old_price'] ?? ''));
    $data['stock']             = trim((string) ($_POST['stock'] ?? '0'));
    $data['status']            = (string) ($_POST['status'] ?? 'active');
    $data['featured']          = isset($_POST['featured']) ? 1 : 0;

    if (mb_strlen($data['name']) < 3) {
        $errors[] = 'اسم المنتج مطلوب (3 أحرف على الأقل).';
    }
    if (mb_strlen($data['short_description']) > 300) {
        $errors[] = 'الوصف القصير يجب ألا يتجاوز 300 حرف.';
    }
    if ($data['price'] === '' || !is_numeric($data['price']) || (float) $data['price'] < 0) {
        $errors[] = 'يرجى إدخال سعر صحيح للمنتج.';
    }
    if ($data['old_price'] !== '' && (!is_numeric($data['old_price']) || (float) $data['old_price'] < 0)) {
        $errors[] = 'السعر القديم غير صحيح.';
    }
    if ($data['stock'] === '' || !ctype_digit($data['stock'])) {
        $errors[] = 'المخزون يجب أن يكون رقماً صحيحاً (0 أو أكثر).';
    }
    if (!in_array($data['status'], ['active', 'draft'], true)) {
        $data['status'] = 'active';
    }
    if ($data['category_id'] > 0) {
        $catCheck = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $catCheck->execute([$data['category_id']]);
        if (!$catCheck->fetch()) {
            $data['category_id'] = 0;
        }
    }

    $newImage = upload_product_image($_FILES['image'] ?? [], $currentImage);
    if (!$newImage['ok']) {
        $errors[] = $newImage['error'];
    }
    $imageName = $newImage['image'];

    if (!empty($_POST['remove_image']) && (!isset($_FILES['image']['error']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE)) {
        $imageName = null;
    }

    if (!$errors) {
        $params = [
            $data['category_id'] > 0 ? $data['category_id'] : null,
            $data['name'],
            $data['short_description'] !== '' ? $data['short_description'] : null,
            $data['description'] !== '' ? $data['description'] : null,
            $data['details'] !== '' ? $data['details'] : null,
            (float) $data['price'],
            $data['old_price'] !== '' ? (float) $data['old_price'] : null,
            (int) $data['stock'],
            $imageName,
            (int) $data['featured'],
            $data['status'],
        ];

        if ($isEdit) {
            $params[] = $id;
            $update = $pdo->prepare(
                'UPDATE products SET category_id = ?, name = ?, short_description = ?, description = ?, details = ?,
                 price = ?, old_price = ?, stock = ?, image = ?, featured = ?, status = ? WHERE id = ?'
            );
            $update->execute($params);
            flash('success', 'تم تحديث المنتج «' . $data['name'] . '» بنجاح.');
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO products
                 (category_id, name, short_description, description, details, price, old_price, stock, image, featured, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute($params);
            $id = (int) $pdo->lastInsertId();
            flash('success', 'تمت إضافة المنتج «' . $data['name'] . '» بنجاح.');
        }

        // حفظ المقاسات المتاحة للمنتج
        $pdo->prepare('DELETE FROM product_sizes WHERE product_id = ?')->execute([$id]);
        if ($postedSizes) {
            $sizeInsert = $pdo->prepare('INSERT INTO product_sizes (product_id, size_id) VALUES (?, ?)');
            foreach ($postedSizes as $sizeId) {
                $sizeInsert->execute([$id, $sizeId]);
            }
        }

        // حذف صور المعرض المحددة
        if ($removeGallery) {
            $marks = implode(',', array_fill(0, count($removeGallery), '?'));
            $select = $pdo->prepare("SELECT image FROM product_images WHERE id IN ($marks) AND product_id = ?");
            $select->execute(array_merge($removeGallery, [$id]));
            $oldFiles = $select->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare("DELETE FROM product_images WHERE id IN ($marks) AND product_id = ?")
                ->execute(array_merge($removeGallery, [$id]));
            foreach ($oldFiles as $oldFile) {
                $path = UPLOAD_PATH . '/' . basename((string) $oldFile);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        // إضافة صور المعرض الجديدة
        if ($galleryUploads) {
            $sortStart = (int) $pdo->query(
                'SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ' . (int) $id
            )->fetchColumn();
            $galleryInsert = $pdo->prepare('INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)');
            foreach ($galleryUploads as $galleryImage) {
                $sortStart++;
                $galleryInsert->execute([$id, $galleryImage, $sortStart]);
            }
            $gallery = product_gallery($id);
        }

        redirect(url('admin/products.php'));
    }
}

$pageTitle = $isEdit ? 'تعديل منتج' : 'إضافة منتج';
$pageSubtitle = $isEdit ? 'حدّثي بيانات القطعة وصورها' : 'أضيفي قطعة جديدة إلى تشكيلة المتجر';
$activePage = 'products';

require __DIR__ . '/includes/header.php';
?>

<div class="admin-page-bar">
    <a class="admin-btn admin-btn-ghost" href="<?= url('admin/products.php') ?>">← العودة إلى المنتجات</a>
    <button class="admin-btn admin-btn-primary" type="submit" form="productForm">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7 9 18l-5-5"/></svg>
        <?= $isEdit ? 'حفظ التعديلات' : 'إضافة المنتج' ?>
    </button>
</div>

<?php if ($errors): ?>
    <div class="admin-alert admin-alert-danger">
        <strong>يرجى تصحيح ما يلي:</strong>
        <ul>
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="admin-form-grid" id="productForm" method="post" action="<?= url('admin/product-form.php' . ($isEdit ? '?id=' . $id : '')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="admin-form-main">
        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><h2>معلومات أساسية</h2><p>الاسم والوصف الذي سيظهر في صفحة المنتج</p></div>
            </div>

            <div class="admin-field-grid">
                <label class="admin-field admin-field-full">
                    <span>اسم المنتج <b>*</b></span>
                    <input type="text" name="name" value="<?= e($data['name']) ?>" required placeholder="مثال: قفطان مراكش الملكي">
                </label>

                <label class="admin-field admin-field-full">
                    <span>وصف قصير</span>
                    <input type="text" name="short_description" data-counter="shortDescCount" maxlength="300" value="<?= e($data['short_description']) ?>" placeholder="جملة قصيرة تظهر تحت اسم المنتج">
                    <small class="admin-field-help"><span id="shortDescCount">0</span>/300 حرف</small>
                </label>

                <label class="admin-field admin-field-full">
                    <span>الوصف الكامل</span>
                    <textarea name="description" rows="6" placeholder="احكِ تفاصيل القطعة: القصة، القماش، المناسبة…"><?= e($data['description']) ?></textarea>
                </label>

                <label class="admin-field admin-field-full">
                    <span>التفاصيل والمقاسات</span>
                    <textarea name="details" rows="5" placeholder="الخامة: قطن&#10;المقاسات: S / M / L&#10;العناية: غسيل لطيف"><?= e($data['details']) ?></textarea>
                    <small class="admin-field-help">كل سطر سيظهر منفصلاً في صفحة المنتج.</small>
                </label>
            </div>
        </section>
    </div>

    <aside class="admin-form-side">
        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>النشر</h2><p>تحكم في ظهور المنتج</p></div></div>
            <div class="admin-field-grid">
                <label class="admin-field">
                    <span>الحالة</span>
                    <select name="status" class="admin-select">
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>منشور في المتجر</option>
                        <option value="draft" <?= $data['status'] === 'draft' ? 'selected' : '' ?>>مسودة (غير مرئي)</option>
                    </select>
                </label>

                <label class="admin-check">
                    <input type="checkbox" name="featured" value="1" <?= (int) $data['featured'] === 1 ? 'checked' : '' ?>>
                    <span><strong>منتج مميز</strong><small>يظهر في قسم «مختاراتنا» بالصفحة الرئيسية</small></span>
                </label>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>السعر والمخزون</h2><p>بالدرهم المغربي</p></div></div>
            <div class="admin-field-grid">
                <label class="admin-field">
                    <span>السعر الحالي <b>*</b></span>
                    <input type="number" name="price" min="0" step="0.01" value="<?= e((string) $data['price']) ?>" required placeholder="0.00">
                </label>
                <label class="admin-field">
                    <span>السعر قبل الخصم <small>(اختياري)</small></span>
                    <input type="number" name="old_price" min="0" step="0.01" value="<?= e((string) $data['old_price']) ?>" placeholder="0.00">
                </label>
                <label class="admin-field">
                    <span>الكمية في المخزون <b>*</b></span>
                    <input type="number" name="stock" min="0" step="1" value="<?= e((string) $data['stock']) ?>" required>
                    <small class="admin-field-help">عند وصول المخزون لـ 0 يظهر «نفد المخزون» تلقائياً.</small>
                </label>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><h2>المقاسات المتوفرة</h2><p>حدّدي المقاسات التي تستطيع العميلة اختيارها</p></div>
                <a class="admin-link" href="<?= url('admin/sizes.php') ?>">إدارة المقاسات ←</a>
            </div>
            <?php $sizeList = all_sizes(); ?>
            <?php if ($sizeList): ?>
                <div class="admin-size-options">
                    <?php foreach ($sizeList as $size): ?>
                        <label class="admin-size-check">
                            <input type="checkbox" name="sizes[]" value="<?= (int) $size['id'] ?>" <?= in_array((int) $size['id'], $selectedSizes, true) ? 'checked' : '' ?>>
                            <span><?= e($size['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small class="admin-field-help">إن لم تحددي أي مقاس، ستُضاف القطعة للسلة بدون مقاس.</small>
            <?php else: ?>
                <p class="admin-field-help">لم تُضف أي مقاس بعد. <a href="<?= url('admin/sizes.php') ?>">أضيفي مقاسات من هنا</a>.</p>
            <?php endif; ?>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head"><div><h2>صورة المنتج</h2><p>JPG أو PNG أو WEBP — أقل من 5 ميغابايت</p></div></div>

            <div class="admin-image-preview" id="imagePreview">
                <?php if ($currentImage): ?>
                    <img src="<?= product_image($currentImage) ?>" alt="صورة المنتج الحالية" data-preview-img>
                <?php else: ?>
                    <span class="admin-image-placeholder" data-preview-img>لا توجد صورة</span>
                <?php endif; ?>
            </div>

            <label class="admin-file-field">
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-image-input data-preview-target="#imagePreview">
                <span>اختيار صورة من الجهاز</span>
            </label>

            <?php if ($currentImage): ?>
                <label class="admin-check admin-check-danger">
                    <input type="checkbox" name="remove_image" value="1">
                    <span><strong>حذف الصورة الحالية</strong><small>سيتم استخدام صورة افتراضية</small></span>
                </label>
            <?php endif; ?>

            <p class="admin-field-help">يُفضّل استخدام صورة عمودية بنسبة 3:4 للحصول على أفضل عرض. هذه هي الصورة الأساسية التي تظهر في بطاقات المتجر والسلة.</p>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-head">
                <div><h2>صور إضافية (معرض المنتج)</h2><p>أضيفي أكثر من صورة للمنتج الواحد</p></div>
            </div>

            <?php if ($gallery): ?>
                <div class="admin-gallery-grid">
                    <?php foreach ($gallery as $galleryItem): ?>
                        <label class="admin-gallery-item">
                            <img src="<?= product_image($galleryItem['image']) ?>" alt="صورة إضافية" loading="lazy" width="110" height="138">
                            <span class="admin-gallery-remove">
                                <input type="checkbox" name="remove_gallery[]" value="<?= (int) $galleryItem['id'] ?>">
                                <span>حذف</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small class="admin-field-help">حدّدي «حذف» على الصور التي تريدين إزالتها، ثم احفظي التعديلات.</small>
            <?php else: ?>
                <p class="admin-field-help">لا توجد صور إضافية بعد. أضيفي صوراً لتظهر كمعرض في صفحة المنتج.</p>
            <?php endif; ?>

            <label class="admin-file-field">
                <input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" data-gallery-input>
                <span>اختيار صور متعددة</span>
            </label>
            <small class="admin-field-help"><span data-gallery-count>لم يتم اختيار صور</span> — يمكنك تحديد عدة صور دفعة واحدة (أقل من 5 ميغابايت للصورة).</small>
        </section>

        <section class="admin-panel">
            <label class="admin-field">
                <span>التصنيف</span>
                <select name="category_id" class="admin-select">
                    <option value="0">بدون تصنيف</option>
                    <?php foreach (all_categories() as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) $data['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </section>
    </aside>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
