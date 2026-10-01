<?php /** @var array $p; @var bool $sent */ $tags = Hub::lines($p['specialties']); $services = Hub::lines($p['services']); ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <a href="<?= e(url('/apiterapevty/')) ?>">Апітерапевти</a> / <?= e($p['name']) ?></div>
  <?php if ($p['verified']): ?><span class="verified">Перевірено Медоїжкою</span><?php endif; ?>
  <h1 style="font-size:clamp(34px,5vw,60px);margin-top:10px"><?= e($p['name']) ?></h1>
  <p class="lead"><?= e($p['title']) ?><?= $p['city'] ? ' · ' . e($p['city']) : '' ?><?= $p['online'] ? ' · онлайн' : '' ?></p>
</div></section>
<div class="wrap"><div class="pr-layout">
  <div class="prose">
    <?php if ($tags): ?><div class="pp-tags" style="margin-bottom:22px"><?php foreach ($tags as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($p['bio']): ?><?= nl2br(e($p['bio'])) ?><?php endif; ?>
    <?php if ($services): ?><h2>Послуги</h2><ul><?php foreach ($services as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($p['price_from']): ?><p><b>Орієнтовна вартість:</b> від <?= (int)$p['price_from'] ?> ₴</p><?php endif; ?>
  </div>
  <aside class="sticky"><?= View::partial('directory/_book_form', ['action' => url('/apiterapevty/' . slug_enc($p['slug']) . '/zapys/'), 'title' => 'Записатися на консультацію', 'sent' => $sent, 'withGuests' => false]) ?></aside>
</div></div>
