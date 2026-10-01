<?php
/**
 * Сторінка товару Медоїжки.
 * @var array $p, $variants, $attrs, $images, $related, $variant_data; @var ?array $cat; @var ?float $price, $old_price
 */
$first = $variants[0] ?? null;
$pr = $price !== null && (float)$price > 0 ? (float)$price : null;
$fmt = fn($v) => number_format((float)$v, 0, ',', ' ') . ' ₴';
$photos = $images ?: [['path' => Catalog::photo($p)]];
$phone = Content::title('contact_phone');
$brands = Catalog::brandsOf($p);
$hasDesc = trim(strip_tags((string)$p['description'])) !== '' && $p['description'] !== ($p['short_desc'] ?? '');
$isOut = $avail === Catalog::AVAIL_OUT;
// Фасовка, якої немає (коли інші є), лишається у виборі, але неактивна — з поміткою «немає».
// «Під замовлення» й курс від складу не залежать.
$varOk = fn(array $v) => $avail !== Catalog::AVAIL_IN || !empty($p['made_to_order']) || Courses::isCourse($p) || (int)$v['qty'] > 0;
$checkedIdx = 0;
foreach ($variant_data as $i => $v) if ($varOk($v)) { $checkedIdx = $i; break; }
?>
<div class="wrap pd-crumbs crumbs">
  <a href="<?= e(url('/')) ?>">Головна</a> / <a href="<?= e(url('/shop/')) ?>">Крамниця</a><?php if ($cat): ?> / <a href="<?= e(url(shop_path($cat['slug']))) ?>"><?= e($cat['name']) ?></a><?php endif; ?>
</div>

<section class="wrap pd">
  <div class="pd-gal">
    <div class="pd-main"><img id="pdMain" src="<?= e(asset($photos[0]['path'])) ?>" alt="<?= e($p['name']) ?>" fetchpriority="high"></div>
    <?php if (count($photos) > 1): ?>
      <div class="pd-thumbs">
        <?php foreach ($photos as $i => $im): ?>
          <button type="button" class="<?= $i ? '' : 'on' ?>" data-src="<?= e(asset($im['path'])) ?>"><img src="<?= e(asset(Images::displayThumb($im['path']))) ?>" alt=""></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="pd-info">
    <?php if ($cat): ?><a class="pd-cat" href="<?= e(url(shop_path($cat['slug']))) ?>"><?= e($cat['name']) ?></a><?php endif; ?>
    <h1><?= e($p['name']) ?></h1>
    <?php if ($brands): ?><div class="pd-brand">Виробник: <?php foreach ($brands as $i => $b): ?><?= $i ? ', ' : '' ?><a href="<?= e(url('/brand/' . slug_enc($b['slug']) . '/')) ?>"><?= e($b['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php if (!empty($p['short_desc'])): ?><p class="pd-short"><?= e(mb_strimwidth($p['short_desc'], 0, 320, '…')) ?></p><?php endif; ?>

    <?php if (count($variants) > 1 && isset($variant_data[$checkedIdx]['price'])) $pr = (float)$variant_data[$checkedIdx]['price'] ?: $pr; ?>
    <div class="pd-price<?= $isOut ? ' is-out' : '' ?>"><span id="pdPrice"><?= $pr !== null ? e($fmt($pr)) : 'Ціну уточнюйте' ?></span><?php if ($old_price !== null && $pr !== null && !$isOut): ?> <s><?= e($fmt($old_price)) ?></s><?php endif; ?></div>

    <?php if ($avail === Catalog::AVAIL_IN && !Courses::isCourse($p)): ?>
      <div class="pd-stock is-in"><i></i>В наявності</div>
    <?php elseif ($avail === Catalog::AVAIL_ORDER): ?>
      <div class="pd-stock is-order"><i></i><?= e($made_to_order_note) ?></div>
    <?php elseif ($isOut): ?>
      <div class="pd-stock is-out"><i></i>Немає в наявності</div>
    <?php endif; ?>

    <?php if ($isOut): ?>
    <div class="pd-watch" id="watch">
      <p>Готуємо нову партію. Залиште запит — напишемо, щойно товар зʼявиться.</p>
      <?php if (!empty($watching)): ?>
        <p class="pd-watch-ok">Ви вже в черзі — повідомимо, щойно зʼявиться.</p>
      <?php elseif (Auth::check()): ?>
        <form method="post" action="<?= e(url('/stock/watch')) ?>"><?= Csrf::field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="<?= e(product_path($p['slug'])) ?>">
          <button class="btn btn-gold" type="submit">Повідомити, коли зʼявиться</button>
        </form>
      <?php else: ?>
        <button class="btn btn-gold" type="button" data-auth-open>Повідомити, коли зʼявиться</button>
      <?php endif; ?>
      <?php if ($phone !== ''): ?><a class="btn btn-line" href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>">Запитати за телефоном</a><?php endif; ?>
      <?php if (empty($watching) && !Auth::check()): ?><small>Попросимо увійти — щоб було куди написати.</small><?php endif; ?>
    </div>
    <?php elseif ($pr !== null): ?>
    <form class="pd-buy add-cart-form" method="post" action="<?= e(url('/cart/add')) ?>" data-product-name="<?= e($p['name']) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="back" value="<?= e(product_path($p['slug'])) ?>">
      <?php if (count($variants) > 1): ?>
        <fieldset class="pd-vars"><legend>Оберіть варіант</legend>
          <?php foreach ($variant_data as $i => $v): ?>
            <?php $ok = $varOk($v); ?>
            <label class="<?= $ok ? '' : 'is-out' ?>"><input type="radio" name="variant_id" value="<?= (int)$v['id'] ?>" data-price="<?= $v['price'] !== null ? e($fmt($v['price'])) : 'Ціну уточнюйте' ?>"<?= $i === $checkedIdx ? ' checked' : '' ?><?= $ok ? '' : ' disabled' ?>><span><?= e($v['name']) ?><?= $ok ? '' : ' <small>немає</small>' ?></span></label>
          <?php endforeach; ?>
        </fieldset>
      <?php elseif ($first): ?><input type="hidden" name="variant_id" value="<?= (int)$first['id'] ?>"><?php endif; ?>
      <div class="pd-row">
        <div class="pd-qty"><button type="button" data-q="-1" aria-label="Менше">−</button><input type="number" name="qty" value="1" min="1" max="99" aria-label="Кількість"><button type="button" data-q="1" aria-label="Більше">+</button></div>
        <button class="btn btn-gold pd-add" type="submit">Додати в кошик</button>
      </div>
    </form>
    <?php else: ?>
      <div class="pd-row"><?php if ($phone !== ''): ?><a class="btn btn-gold" href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>">Замовити за телефоном</a><?php endif; ?><a class="btn btn-line" href="<?= e(url('/contacts/')) ?>">Написати нам</a></div>
    <?php endif; ?>

    <ul class="pd-perks">
      <li><b>Доставка Новою Поштою</b><span>по всій Україні</span></li>
      <li><b>Самовивіз у Києві</b><span><?= e(Content::title('contact_address', 'Медова крамниця')) ?></span></li>
      <li><b>Оплата</b><span>карткою на сайті або при отриманні</span></li>
    </ul>
    <?php if ($phone !== ''): ?><p class="pd-call">Питання щодо товару? <a href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"><?= e($phone) ?></a></p><?php endif; ?>
  </div>
</section>

<?php if ($hasDesc || $attrs): ?>
<section class="wrap pd-more">
  <?php if ($hasDesc): ?>
    <div class="pd-desc"><h2>Опис</h2><div class="prose"><?= rich($p['description']) ?></div></div>
  <?php endif; ?>
  <?php if ($attrs): ?>
    <aside class="pd-specs"><h2>Характеристики</h2>
      <dl><?php foreach ($attrs as $a): ?><div><dt><?= e($a['name']) ?></dt><dd><?= e($a['value']) ?></dd></div><?php endforeach; ?></dl>
    </aside>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="sec bg-white">
  <div class="wrap">
    <div class="sec-head"><div><div class="kicker">Також радимо</div><h2>З цієї категорії</h2></div></div>
    <div class="pgrid"><?php foreach ($related as $prod) echo View::partial('partials/m_card', ['prod' => $prod]); ?></div>
  </div>
</section>
<?php endif; ?>

<script>
(function () {
  var main = document.getElementById('pdMain');
  document.querySelectorAll('.pd-thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
      main.src = b.dataset.src;
      document.querySelectorAll('.pd-thumbs button').forEach(function (x) { x.classList.toggle('on', x === b); });
    });
  });
  var price = document.getElementById('pdPrice');
  document.querySelectorAll('.pd-vars input').forEach(function (r) {
    r.addEventListener('change', function () { if (r.checked) price.textContent = r.dataset.price; });
  });
  document.querySelectorAll('.pd-qty button').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = b.parentNode.querySelector('input');
      i.value = Math.min(99, Math.max(1, (parseInt(i.value, 10) || 1) + parseInt(b.dataset.q, 10)));
    });
  });
})();
</script>
<script>
/* GA4: перегляд товару */
mzTrack('view_item', { value: <?= json_js($pr !== null ? (float)$pr : 0) ?>, items: [{ item_id: <?= json_js((string)$p['id']) ?>, item_name: <?= json_js($p['name']) ?>, item_category: <?= json_js($cat['name'] ?? '') ?>, price: <?= json_js($pr !== null ? (float)$pr : 0) ?> }] });
</script>
