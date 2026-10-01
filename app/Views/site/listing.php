<?php /** @var array $row, $products; @var string $kind */ ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <a href="<?= e(url('/shop/')) ?>">Крамниця</a> / <?= e($row['name']) ?></div>
  <div class="kicker"><?= e($kind) ?></div>
  <h1 style="font-size:clamp(32px,5vw,58px)"><?= e($row['name']) ?></h1>
  <?php if (!empty($row['description'])): ?><p class="lead"><?= e($row['description']) ?></p><?php endif; ?>
</div></section>
<section class="section"><div class="wrap">
  <?php if (!$products): ?><p class="dim">У цьому розділі поки немає товарів.</p><?php else: ?>
  <div class="grid">
    <?php foreach ($products as $prod) echo View::partial('partials/product_card', ['prod' => $prod]); ?>
  </div><?php endif; ?>
</div></section>
