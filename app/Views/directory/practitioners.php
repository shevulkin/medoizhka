<?php /** @var array $items, $f, $regions */ ?>
<section class="pg-hero lattice"><div class="wrap">
  <div class="crumbs"><a href="<?= e(url('/')) ?>">Головна</a> / Апітерапевти</div>
  <h1>Апітерапевти</h1>
  <p class="lead">Фахівці, які працюють з продуктами бджільництва для здоров’я. Оберіть за містом, спеціалізацією чи форматом і залиште заявку: без реєстрації й оплати наперед.</p>
  <form class="filters" method="get" action="<?= e(url('/apiterapevty/')) ?>">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Спеціалізація чи місто" style="padding:12px 14px;border:2px solid var(--ink);min-width:240px">
    <select name="region" style="padding:12px 14px;border:2px solid var(--ink)"><option value="">Уся Україна</option>
      <?php foreach ($regions as $r): ?><option<?= $f['region'] === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select>
    <label class="chip"><input type="checkbox" name="online" value="1"<?= $f['online'] ? ' checked' : '' ?> style="width:auto;margin-right:6px"> Онлайн</label>
    <button class="btn btn-gold btn-sm" type="submit">Знайти</button>
  </form>
</div></section>
<section class="section"><div class="wrap">
  <?php if (!$items): ?>
    <div class="form-card" style="max-width:640px"><h2 style="font-size:26px">Поки нікого не знайдено</h2>
      <p>Каталог щойно наповнюється. Ви апітерапевт і хочете бути тут? <a href="<?= e(url('/contacts/')) ?>">Напишіть нам</a>.</p></div>
  <?php else: ?>
    <div class="mh-grid" style="grid-template-columns:repeat(auto-fill,minmax(min(300px,100%),1fr))">
      <?php foreach ($items as $p) echo View::partial('directory/_practitioner_card', ['p' => $p]); ?>
    </div>
  <?php endif; ?>
</div></section>
