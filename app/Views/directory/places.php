<?php /** @var array $items, $f, $regions */ ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / Пасіки й апібудиночки</div>
  <h1>Пасіки, апібудиночки й майстер-класи</h1>
  <p class="lead">Поїдьте до бджіл: платні відвідування пасік з розповіддю пасічника, відпочинок в апібудиночках і майстер-класи. Залиште заявку, власник зв’яжеться з вами.</p>
  <div class="filters">
    <a class="chip<?= $f['kind'] === '' ? ' active' : '' ?>" href="<?= e(url('/pasiky/')) ?>">Усі</a>
    <?php foreach (Hub::PLACE_KINDS as $k => [$one, $many]): ?>
      <a class="chip<?= $f['kind'] === $k ? ' active' : '' ?>" href="<?= e(url('/pasiky/?type=' . $k)) ?>"><?= e($many) ?></a>
    <?php endforeach; ?>
  </div>
</div></section>
<section class="section"><div class="wrap">
  <?php if (!$items): ?>
    <div class="form-card" style="max-width:640px"><h2 style="font-size:26px">Місць поки немає</h2>
      <p>Маєте пасіку чи апібудиночок? <a href="<?= e(url('/contacts/')) ?>">Розмістіть його тут</a>.</p></div>
  <?php else: ?>
    <div class="mh-grid" style="grid-template-columns:repeat(auto-fill,minmax(min(300px,100%),1fr))">
      <?php foreach ($items as $p) echo View::partial('directory/_place_card', ['p' => $p]); ?>
    </div>
  <?php endif; ?>
</div></section>
