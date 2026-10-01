<?php /** @var array $p; @var bool $sent */ $am = Hub::lines($p['amenities']); ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <a href="<?= e(url('/pasiky/')) ?>">Пасіки й апібудиночки</a> / <?= e($p['name']) ?></div>
  <span class="mh-badge"><?= e(Hub::kindLabel($p['kind'])) ?></span>
  <h1 style="font-size:clamp(34px,5vw,60px);margin-top:10px"><?= e($p['name']) ?></h1>
  <p class="lead"><?= e($p['summary']) ?></p>
  <div class="pp-meta" style="margin-top:16px;font-size:15px"><?php if ($p['city']): ?><span><?= e($p['city']) ?><?= $p['region'] ? ', ' . e($p['region']) : '' ?></span><?php endif; ?><?php if ($p['duration']): ?><span><?= e($p['duration']) ?></span><?php endif; ?><?php if ($p['capacity']): ?><span>до <?= (int)$p['capacity'] ?> осіб</span><?php endif; ?><?php if ($p['price']): ?><span><b>від <?= (int)$p['price'] ?> ₴</b> <?= e($p['price_note']) ?></span><?php endif; ?></div>
</div></section>
<div class="wrap"><div class="place-layout">
  <div class="prose">
    <?= nl2br(e($p['description'])) ?>
    <?php if ($am): ?><h2>Що входить</h2><ul class="mh-list"><?php foreach ($am as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($p['address']): ?><p><b>Адреса:</b> <?= e($p['address']) ?></p><?php endif; ?>
  </div>
  <aside class="sticky"><?= View::partial('directory/_book_form', ['action' => url('/pasiky/' . slug_enc($p['slug']) . '/zapys/'), 'title' => 'Залишити заявку на візит', 'sent' => $sent, 'withGuests' => true]) ?></aside>
</div></div>
