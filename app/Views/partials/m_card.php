<?php
/**
 * Картка товару Медоїжки. Одна на всі списки: головна, каталог, теги, бренди.
 * Кладе в кошик одразу, якщо вибору немає; з варіантами — веде на сторінку товару.
 * @var array $prod
 */
$vars = Catalog::variants((int)$prod['id']);
$first = $vars[0] ?? null;
[$pr, $old] = Catalog::price($prod, $first);
if ($pr !== null && (float)$pr <= 0) $pr = null;
$cat = $prod['_cat'] ?? DB::val('SELECT name FROM categories WHERE id = ?', [$prod['category_id']]);
$ph = Catalog::photo($prod);
$href = product_url($prod['slug']);
?>
<article class="pc">
  <a class="pc-ph" href="<?= e($href) ?>">
    <img src="<?= e(asset(Images::displayThumb($ph))) ?>" alt="<?= e($prod['name']) ?>" loading="lazy">
    <?php if ($old !== null): ?><span class="pc-flag">Знижка</span><?php elseif (!empty($prod['featured'])): ?><span class="pc-flag">Хіт</span><?php endif; ?>
  </a>
  <div class="pc-bd">
    <span class="pc-cat"><?= e((string)$cat) ?></span>
    <h3 class="pc-name"><a href="<?= e($href) ?>"><?= e($prod['name']) ?></a></h3>
    <?php if (count($vars) > 1): ?><span class="pc-vars"><?= e(implode(' · ', array_map(fn($v) => $v['name'], array_slice($vars, 0, 4)))) ?></span><?php endif; ?>
    <div class="pc-foot">
      <span class="pc-price"><?php if ($pr === null): ?><small>Ціну уточнюйте</small><?php else: ?><?= count($vars) > 1 ? '<small>від</small> ' : '' ?><?= e(number_format((float)$pr, 0, ',', ' ')) ?> ₴<?php if ($old !== null): ?> <s><?= e(number_format((float)$old, 0, ',', ' ')) ?></s><?php endif; ?><?php endif; ?></span>
      <?php if (count($vars) > 1 || $pr === null): ?>
        <a class="pc-btn" href="<?= e($href) ?>" aria-label="Обрати: <?= e($prod['name']) ?>"><?= $pr === null ? 'Детальніше' : 'Обрати' ?></a>
      <?php else: ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>" class="add-cart-form" data-product-name="<?= e($prod['name']) ?>"><?= Csrf::field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
          <?php if ($first): ?><input type="hidden" name="variant_id" value="<?= (int)$first['id'] ?>"><?php endif; ?>
          <input type="hidden" name="back" value="<?= e(request_path()) ?>">
          <button class="pc-btn" type="submit" aria-label="До кошика: <?= e($prod['name']) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 7h14l-1.2 12.1a1 1 0 0 1-1 .9H7.2a1 1 0 0 1-1-.9z"/><path d="M9 7V5.5a3 3 0 0 1 6 0V7"/></svg>
            <span>У кошик</span>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</article>
