<?php /** @var array $page; @var string $body */ ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <?= e($page['title']) ?></div>
  <h1 style="font-size:clamp(32px,5vw,58px)"><?= e($page['title']) ?></h1>
</div></section>
<section class="section"><div class="wrap"><div class="prose"><?= $body ?></div></div></section>
