<?php /** @var array $page; @var string $body */ ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / <?= e($page['title']) ?></div>
  <h1 style="font-size:clamp(32px,5vw,58px)"><?= e($page['title']) ?></h1>
</div></section>
<section class="section"><div class="wrap"><div class="prose"><?= $body ?></div>
  <?php if (($page['slug'] ?? '') === 'contacts' && Catalog::stores()): ?>
    <?php /* Контакти ведуть до адрес і графіка точок: так вони пов'язані, а в меню лишається один пункт */ ?>
    <div class="pg-where">
      <div><b>Де нас знайти</b><span>Адреси, графік роботи й маршрут до наших точок продажу.</span></div>
      <a class="btn btn-gold" href="<?= e(url('/stores')) ?>">Наші адреси →</a>
    </div>
  <?php endif; ?>
</div></section>
