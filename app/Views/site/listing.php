<?php /** @var array $row, $products; @var string $kind */ ?>
<section class="shop-hero">
  <div class="wrap">
    <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <a href="<?= e(url('/shop/')) ?>">Крамниця</a> / <?= e($row['name']) ?></div>
    <div class="kicker"><?= e($kind) ?></div>
    <h1><?= e($row['name']) ?></h1>
    <?php if (!empty($row['description'])): ?><p class="lead"><?= e(mb_strimwidth(trim(strip_tags((string)$row['description'])), 0, 260, '…')) ?></p><?php endif; ?>
  </div>
</section>
<section class="sec"><div class="wrap">
  <?php if (!$products): ?>
    <div class="shop-empty"><h2>Тут поки порожньо</h2><p><a href="<?= e(url('/shop/')) ?>">Перегляньте всю крамницю</a>.</p></div>
  <?php else: ?>
    <?= View::partial('partials/m_grid', ['products' => $products]) ?>
  <?php endif; ?>
</div></section>
