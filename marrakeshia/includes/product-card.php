<?php
/** @var array $product */
$productStock = (int) $product['stock'];
$discount     = discount_percent($product);
$productLink  = url('product.php?id=' . (int) $product['id']);
?>
<article class="product-card">
    <a class="product-media" href="<?= $productLink ?>">
        <img src="<?= product_image($product['image']) ?>" alt="<?= e($product['name']) ?>" loading="lazy" width="600" height="800">
        <?php if ($discount > 0): ?>
            <span class="product-badge badge-sale">-<?= $discount ?>%</span>
        <?php endif; ?>
        <?php if ($productStock < 1): ?>
            <span class="product-badge badge-out">نفد المخزون</span>
        <?php elseif ($productStock <= 3): ?>
            <span class="product-badge badge-low">آخر <?= $productStock ?> قطع</span>
        <?php endif; ?>
        <span class="product-quick">عرض التفاصيل</span>
    </a>

    <div class="product-body">
        <?php if (!empty($product['category_name'])): ?>
            <span class="product-cat"><?= e($product['category_name']) ?></span>
        <?php endif; ?>
        <h3 class="product-name"><a href="<?= $productLink ?>"><?= e($product['name']) ?></a></h3>

        <div class="product-price">
            <span class="price-now"><?= money($product['price']) ?></span>
            <?php if (!empty($product['old_price']) && (float) $product['old_price'] > (float) $product['price']): ?>
                <span class="price-old"><?= money($product['old_price']) ?></span>
            <?php endif; ?>
        </div>

        <form class="js-add-to-cart product-add" method="post" action="<?= url('cart-action.php') ?>" data-product-name="<?= e($product['name']) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <button class="btn btn-dark btn-block" type="submit" <?= $productStock < 1 ? 'disabled' : '' ?>>
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 8h14l-1.2 11H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                <?= $productStock < 1 ? 'نفد المخزون' : 'أضف إلى السلة' ?>
            </button>
        </form>
    </div>
</article>
