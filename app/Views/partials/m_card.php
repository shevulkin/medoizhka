<?php
/**
 * Картка товару Медоїжки. Одна на всі списки: головна, каталог, теги, бренди.
 * Кладе в кошик одразу, якщо вибору немає; з варіантами — веде на сторінку товару.
 * Відсутній товар — притлумлений, з чесним «Немає в наявності» і кнопкою «Повідомити»
 * (вона веде на сторінку товару, де людина стає в чергу очікування).
 * @var array $prod
 */
$vars = Catalog::variants((int)$prod['id']);
$first = $vars[0] ?? null;
[$pr, $old] = Catalog::price($prod, $first);
if ($pr !== null && (float)$pr <= 0) $pr = null;
$cat = $prod['_cat'] ?? DB::val('SELECT name FROM categories WHERE id = ?', [$prod['category_id']]);
$ph = Catalog::photo($prod);
$href = product_url($prod['slug']);
$avail = Catalog::avail($prod);
$isOut = $avail === Catalog::AVAIL_OUT;
// «від …» — найменша ціна серед фасовок, які можна купити, а не ціна першої в списку:
// інакше «Липовий від 150 ₴», хоча є банка за 60, або ціна фасовки, якої немає.
if (count($vars) > 1) {
    $qty = [];
    foreach (Catalog::stockMap((int)$prod['id']) as $byVariant) foreach ($byVariant as $vid => $q) $qty[$vid] = ($qty[$vid] ?? 0) + $q;
    $pool = $avail === Catalog::AVAIL_IN && empty($prod['made_to_order'])
        ? array_filter($vars, fn($v) => ($qty[(int)$v['id']] ?? 0) > 0) : $vars;
    $best = null;
    foreach ($pool ?: $vars as $v) {
        [$vp, $vo] = Catalog::price($prod, $v);
        if ($vp !== null && (float)$vp > 0 && ($best === null || $vp < $best[0])) $best = [$vp, $vo];
    }
    if ($best) [$pr, $old] = $best;
}
?>
<article class="pc<?= $isOut ? ' pc-out' : '' ?>">
  <a class="pc-ph" href="<?= e($href) ?>">
    <?php $ss = Images::cardSrcset($ph); ?><img src="<?= e(asset(Images::displayThumb($ph))) ?>"<?= $ss !== '' ? ' srcset="' . e($ss) . '" sizes="(max-width:700px) 50vw, (max-width:1100px) 33vw, 360px"' : '' ?> alt="<?= e($prod['name']) ?>" loading="lazy">
    <?php if ($isOut): ?><span class="pc-flag pc-flag-out"><?= e(Catalog::outLabel($prod)) ?></span>
    <?php elseif ($old !== null): ?><span class="pc-flag">Знижка</span>
    <?php elseif (!empty($prod['featured'])): ?><span class="pc-flag">Хіт</span>
    <?php elseif (Catalog::isService($prod)): ?><span class="pc-flag pc-flag-svc">Послуга</span><?php endif; ?>
  </a>
  <div class="pc-bd">
    <span class="pc-cat"><?= e((string)$cat) ?></span>
    <h3 class="pc-name"><a href="<?= e($href) ?>"><?= e($prod['name']) ?></a></h3>
    <?php if (count($vars) > 1): ?><span class="pc-vars"><?= e(implode(' · ', array_map(fn($v) => $v['name'], array_slice($vars, 0, 4)))) ?></span><?php endif; ?>
    <?php if ($avail === Catalog::AVAIL_ORDER): ?><span class="pc-note"><?= e(Catalog::madeToOrderShort($prod)) ?></span><?php endif; ?>
    <div class="pc-foot">
      <span class="pc-price"><?php if ($pr === null): ?><small>Ціну уточнюйте</small><?php else: ?><?= count($vars) > 1 ? '<small>від</small> ' : '' ?><?= e(price_num($pr)) ?> ₴<?php if ($old !== null && !$isOut): ?> <s><?= e(price_num($old)) ?></s><?php endif; ?><?php endif; ?></span>
      <?php if ($isOut): ?>
        <a class="pc-btn pc-btn-quiet" href="<?= e($href) ?>#watch" aria-label="Повідомити, коли <?= Catalog::isService($prod) ? 'відновимо' : 'зʼявиться' ?>: <?= e($prod['name']) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>
          <span>Повідомити</span>
        </a>
      <?php elseif (count($vars) > 1 || $pr === null || Catalog::isService($prod)): ?>
        <a class="pc-btn" href="<?= e($href) ?>" aria-label="Обрати: <?= e($prod['name']) ?>"><?= Catalog::isService($prod) ? 'Замовити' : ($pr === null ? 'Детальніше' : 'Обрати') ?></a>
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
