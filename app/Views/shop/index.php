<?php
/**
 * Каталог Медоїжки: категорії збоку, пошук і сортування над сіткою.
 * @var array $categories, $cat_tree, $products, $filters; @var ?array $current_cat, $parent_cat, $brand
 */
$cur = $current_cat;
$title = $cur['name'] ?? ($brand['name'] ?? ($filters['q'] !== '' ? 'Пошук: «' . $filters['q'] . '»' : 'Крамниця'));
$qs = fn(array $over) => ($p = array_filter(array_merge(['q' => $filters['q'], 'sort' => $filters['sort']], $over), fn($v) => $v !== '' && $v !== null)) ? '?' . http_build_query($p) : '';
$base = $cur ? shop_path($cur['slug']) : '/shop/';
$counts = [];
foreach (DB::all("SELECT category_id, COUNT(*) n FROM products WHERE active = 1 AND type <> 'course' GROUP BY category_id") as $r) $counts[(int)$r['category_id']] = (int)$r['n'];
?>
<section class="shop-hero">
  <div class="wrap">
    <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <?php if ($cur): ?><a href="<?= e(url('/shop/')) ?>">Крамниця</a> / <?= e($cur['name']) ?><?php else: ?>Крамниця<?php endif; ?></div>
    <h1><?= e($title) ?></h1>
    <?php if (!empty($cur['description'])): ?><p class="lead"><?= e(mb_strimwidth($cur['description'], 0, 260, '…')) ?></p>
    <?php elseif (!$cur): ?><p class="lead">Мед, продукти бджільництва, натуральна косметика та все для пасіки. Від Медоїжки й перевірених виробників.</p><?php endif; ?>
  </div>
</section>

<div class="wrap shop">
  <aside class="shop-side">
    <nav aria-label="Категорії">
      <h2>Категорії</h2>
      <a href="<?= e(url('/shop/')) ?>"<?= !$cur ? ' class="on"' : '' ?>><span>Усі товари</span><small><?= array_sum($counts) ?></small></a>
      <?php foreach ($cat_tree as $c): if (empty($counts[(int)$c['id']]) && empty($c['children'])) continue; ?>
        <a href="<?= e(url(shop_path($c['slug']))) ?>"<?= ($cur['id'] ?? 0) == $c['id'] ? ' class="on"' : '' ?>><span><?= e($c['name']) ?></span><small><?= $counts[(int)$c['id']] ?? 0 ?></small></a>
        <?php foreach ($c['children'] ?? [] as $k): ?>
          <a class="sub<?= ($cur['id'] ?? 0) == $k['id'] ? ' on' : '' ?>" href="<?= e(url(shop_path($k['slug']))) ?>"><span><?= e($k['name']) ?></span></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="shop-help">
      <b>Потрібна порада?</b>
      <p>Підкажемо сорт і дозування, зберемо замовлення.</p>
      <?php if (($ph = Content::title('contact_phone')) !== ''): ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $ph)) ?>"><?= e($ph) ?></a><?php endif; ?>
    </div>
  </aside>

  <div class="shop-main">
    <form class="shop-bar" method="get" action="<?= e(url($base)) ?>">
      <span class="shop-count"><?= count($products) ?> <?= e(plural(count($products), 'товар', 'товари', 'товарів')) ?></span>
      <label class="shop-q"><span class="sr-only">Пошук</span>
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Пошук у <?= $cur ? 'категорії' : 'крамниці' ?>">
      </label>
      <label class="shop-sort"><span>Сортувати</span>
        <select name="sort" onchange="this.form.submit()">
          <?php foreach (['' => 'За замовчуванням', 'price_asc' => 'Спершу дешевші', 'price_desc' => 'Спершу дорожчі', 'new' => 'Новинки'] as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= e($l) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <?php if (!$products): ?>
      <?php if ($filters['q'] !== '' || $filters['min'] !== '' || $filters['max'] !== '' || $filters['attr'] || $filters['brand']): ?>
        <div class="shop-empty"><h2>Нічого не знайдено</h2><p>Спробуйте інше слово або <a href="<?= e(url('/shop/')) ?>">перегляньте весь каталог</a>.</p></div>
      <?php else: /* розділ, де зараз нічого немає: не «нічого не знайдено» — людина нічого й не шукала */ ?>
        <div class="shop-empty"><h2>Зараз тут порожньо</h2><p>Нова партія вже готується. Поки що <a href="<?= e(url('/shop/')) ?>">перегляньте інші розділи крамниці</a>
          <?php if (($ph = Content::title('contact_phone')) !== ''): ?> або зателефонуйте: <a href="tel:<?= e(preg_replace('~[^\d+]~', '', $ph)) ?>"><?= e($ph) ?></a><?php endif; ?>.</p></div>
      <?php endif; ?>
    <?php else: ?>
      <?= View::partial('partials/m_grid', ['products' => $products, 'cols' => 'pgrid-3']) ?>
    <?php endif; ?>
  </div>
</div>
