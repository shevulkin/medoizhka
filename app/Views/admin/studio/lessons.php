<?php
/**
 * Сторінка відеокурсу: основне, відео, учні.
 * @var array $course, $lessons, $progress, $students, $added; @var ?array $library; @var int $cid; @var bool $has_api
 */
$base = '/admin/lessons?course=' . $cid;
$act = fn(string $a) => '<input type="hidden" name="_action" value="' . $a . '"><input type="hidden" name="course" value="' . $cid . '">';
$fmt = fn(int $s) => $s > 0 ? Lessons::fmt($s) : '';
$total = array_sum(array_map(fn($l) => (int)($l['duration'] ?? 0), $lessons));
$days = $course['access_days'] ?? null;
?>
<div class="admin-head">
  <div>
    <a class="dim" href="<?= e(url('/admin/lessons')) ?>">← Усі відеокурси</a>
    <h1 class="h-serif" style="margin-top:6px"><?= e($course['name']) ?></h1>
    <?= $course['active'] ? '<span class="status-pill st-processing">Опубліковано</span>' : '<span class="status-pill">Чернетка — покупці не бачать</span>' ?>
    <?php if (!feature('courses')): ?><span class="status-pill">Розділ курсів на сайті вимкнено</span><?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-line btn-sm" href="<?= e(url('/admin/products/' . $cid)) ?>">Опис, програма, обкладинка</a>
    <a class="btn btn-line btn-sm" href="<?= e(course_url($course['slug'])) ?>" target="_blank" rel="noopener">На сайті →</a>
  </div>
</div>

<nav class="crs-tabs">
  <a href="#basic">Основне</a><a href="#videos">Відео <span><?= count($lessons) ?></span></a><a href="#students">Учні <span><?= count($students) ?></span></a>
</nav>

<form class="admin-card" id="basic" method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?><?= $act('basic') ?>
  <h2>Основне</h2>
  <div class="form-grid">
    <div class="field"><label>Назва курсу</label><input name="name" value="<?= e($course['name']) ?>" required></div>
    <div class="field"><label>Ціна, ₴</label><input name="price" type="number" min="0" step="1" value="<?= e($course['base_price'] !== null ? (string)(float)$course['base_price'] : '') ?>" placeholder="порожньо — «ціну уточнюйте»"></div>
    <div class="field"><label>Строк доступу після оплати, днів</label><input name="access_days" type="number" min="1" value="<?= e((string)($days ?? '')) ?>" placeholder="порожньо — назавжди"></div>
  </div>
  <label class="checkbox" style="margin:4px 0 16px"><input type="checkbox" name="active"<?= $course['active'] ? ' checked' : '' ?>> Опубліковано — курс видно покупцям і його можна купити</label>
  <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
</form>

<div class="admin-card" id="videos">
  <div class="crs-h">
    <h2>Відео курсу<?php if ($lessons): ?> <span class="dim" style="font-size:15px;font-weight:500">· <?= count($lessons) ?><?= $total ? ' · ' . e($fmt($total)) : '' ?></span><?php endif; ?></h2>
    <?php if ($has_api && $library === null): ?>
      <a class="btn btn-gold btn-sm" href="<?= e(url($base . '&pick=1#videos')) ?>">+ Додати з бібліотеки Bunny</a>
    <?php endif; ?>
  </div>

  <?php if (isset($_GET['pick'])): ?>
    <?php if ($library === null): ?>
      <p class="flash-error" style="padding:12px 14px">Bunny не відповів або API-ключ невірний. Перевірте ключ на сторінці «Відеокурси» → «Налаштування Bunny Stream», або вставте посилання нижче.</p>
    <?php else: ?>
      <form class="crs-pick" method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?><?= $act('add_pick') ?>
        <div class="crs-pick-bar">
          <input type="search" placeholder="Пошук за назвою…" data-pick-search>
          <span class="dim">Позначте відео в тому порядку, в якому їх дивитимуться — вони додадуться в кінець курсу.</span>
        </div>
        <div class="crs-pick-list">
          <?php foreach ($library as $v): $in = isset($added[strtolower($v['guid'])]); ?>
            <label class="crs-pick-row<?= $in ? ' is-in' : '' ?>" data-title="<?= e(mb_strtolower($v['title'])) ?>">
              <input type="checkbox" name="pick[]" value="<?= e($v['guid']) ?>"<?= $in ? ' disabled' : '' ?>>
              <input type="hidden" name="ptitle[<?= e($v['guid']) ?>]" value="<?= e($v['title']) ?>">
              <input type="hidden" name="plen[<?= e($v['guid']) ?>]" value="<?= (int)$v['length'] ?>">
              <span class="t"><?= e($v['title'] !== '' ? $v['title'] : $v['guid']) ?></span>
              <span class="dim"><?= $in ? 'вже в курсі' : e($fmt($v['length'])) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$library): ?><p class="dim">У бібліотеці ще немає відео.</p><?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;margin-top:12px">
          <button class="btn btn-gold btn-sm" type="submit">Додати обрані</button>
          <a class="btn btn-line btn-sm" href="<?= e(url($base . '#videos')) ?>">Закрити список</a>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($lessons): ?>
  <form method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?><?= $act('save') ?>
    <div style="overflow:auto"><table class="admin-table crs-videos" style="width:100%">
      <tr><th style="width:44px">№</th><th style="width:76px">Порядок</th><th>Назва (так її бачить покупець)</th><th>Тривалість</th><th title="Можна дивитись без купівлі — як «пробне» відео">Безкоштовне</th><th></th></tr>
      <?php foreach ($lessons as $i => $l): $lid = (int)$l['id']; ?>
        <tr>
          <td class="dim"><?= $i + 1 ?></td>
          <td class="crs-move">
            <button class="btn btn-line btn-xs" type="submit" form="mv<?= $lid ?>u"<?= $i === 0 ? ' disabled' : '' ?> title="Вище">↑</button>
            <button class="btn btn-line btn-xs" type="submit" form="mv<?= $lid ?>d"<?= $i === count($lessons) - 1 ? ' disabled' : '' ?> title="Нижче">↓</button>
          </td>
          <td><input name="l[<?= $lid ?>][title]" value="<?= e($l['title']) ?>" style="min-width:240px"></td>
          <td class="dim"><?= e($fmt((int)($l['duration'] ?? 0))) ?: '—' ?></td>
          <td style="text-align:center"><input type="checkbox" name="l[<?= $lid ?>][free]" value="1"<?= $l['free_preview'] ? ' checked' : '' ?>></td>
          <td style="white-space:nowrap;text-align:right">
            <a class="btn btn-line btn-xs" href="<?= e(url('/learn/lesson?id=' . $lid)) ?>" target="_blank" rel="noopener" title="Переглянути, як бачить учень">▶</a>
            <button class="btn btn-danger btn-xs" type="submit" form="del<?= $lid ?>" title="Прибрати з курсу">×</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <div style="margin-top:14px"><button class="btn btn-gold btn-sm" type="submit">Зберегти назви</button></div>
  </form>
  <?php foreach ($lessons as $l): $lid = (int)$l['id']; ?>
    <?php foreach (['u' => 'up', 'd' => 'down'] as $k => $dir): ?>
      <form id="mv<?= $lid . $k ?>" method="post" action="<?= e(url('/admin/lessons')) ?>" hidden><?= Csrf::field() ?><?= $act('move') ?>
        <input type="hidden" name="id" value="<?= $lid ?>"><input type="hidden" name="dir" value="<?= $dir ?>"></form>
    <?php endforeach; ?>
    <form id="del<?= $lid ?>" method="post" action="<?= e(url('/admin/lessons')) ?>" hidden onsubmit="return confirm('Прибрати це відео з курсу? Прогрес і нотатки учнів до нього теж зникнуть.')"><?= Csrf::field() ?><?= $act('delete') ?>
      <input type="hidden" name="id" value="<?= $lid ?>"></form>
  <?php endforeach; ?>
  <?php else: ?>
    <p class="dim">Відео ще немає. <?= $has_api ? 'Натисніть «Додати з бібліотеки Bunny» або вставте посилання нижче.' : 'Вставте посилання нижче.' ?></p>
  <?php endif; ?>

  <details class="crs-paste"<?= !$lessons && !$has_api ? ' open' : '' ?>>
    <summary>Вставити посилання на відео</summary>
    <form method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?><?= $act('add_lines') ?>
      <p class="dim">По одному відео в рядку: посилання з Bunny (на плеєр чи на сторінку відео в кабінеті) або його ID. Назву можна дописати поруч чи після «|» — інакше
        <?= $has_api ? 'візьмемо її з Bunny.' : 'буде «Відео 1, 2…», її легко змінити в списку.' ?></p>
      <textarea name="lines" rows="4" style="width:100%" placeholder="https://iframe.mediadelivery.net/play/573243/37d3bbc8-3cbf-423d-aa74-e36fd2a4e4ad | Як обрати місце для пасіки"></textarea>
      <div style="margin-top:10px"><button class="btn btn-gold btn-sm" type="submit">Додати</button></div>
    </form>
    <?php if (!$has_api): ?><p class="dim" style="margin-top:10px">Щоб обирати відео галочками зі списку бібліотеки, вкажіть API-ключ: <a href="<?= e(url('/admin/lessons')) ?>">Відеокурси</a> → «Налаштування Bunny Stream».</p><?php endif; ?>
  </details>
</div>

<div class="admin-card" id="students">
  <h2>Учні</h2>
  <form class="crs-grant" method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?><?= $act('grant') ?>
    <div class="field" style="flex:2;min-width:280px"><label>Відкрити доступ — пошти учнів</label>
      <textarea name="emails" rows="2" placeholder="olena@gmail.com, petro@ukr.net — можна кілька, через кому чи з нового рядка"></textarea></div>
    <div class="field" style="width:190px"><label>На скільки днів</label>
      <input name="days" type="number" min="1" placeholder="<?= $days ? (int)$days . ' (як у курсі)' : 'назавжди' ?>"></div>
    <button class="btn btn-gold btn-sm" type="submit">Відкрити доступ</button>
  </form>
  <p class="dim" style="margin:-4px 0 16px">Купили на сайті — доступ відкривається сам після оплати. Тут — подарунки, перенесення зі старого сайту, оплата готівкою.
    Учень входить на сайт цією поштою (код приходить на неї) і бачить курс у «Мої відеокурси».</p>

  <?php if (!$students): ?>
    <p class="dim">Доступу ще ні в кого немає.</p>
  <?php else: ?>
  <div style="overflow:auto"><table class="admin-table" style="width:100%">
    <tr><th>Учень</th><th>Відкрито</th><th>Доступ до</th><th>Прогрес</th><th></th></tr>
    <?php foreach ($students as $s):
      $pr = $progress[(int)$s['user_id']] ?? null; $w = (int)($pr['watched'] ?? 0);
      $expired = $s['expires_at'] !== null && strtotime((string)$s['expires_at']) < time(); ?>
      <tr>
        <td><?= e($s['uname'] ?: $s['email']) ?><br><span class="dim"><?= e($s['email']) ?></span></td>
        <td><?= e(date('d.m.Y', strtotime((string)$s['granted_at']))) ?>
          <div class="dim"><?= $s['order_number'] ? 'замовлення ' . e($s['order_number']) : 'вручну' ?></div></td>
        <td><?= $s['expires_at'] === null ? 'назавжди' : e(date('d.m.Y', strtotime((string)$s['expires_at']))) ?>
          <?php if ($expired): ?><div><span class="status-pill st-canceled">закінчився</span></div><?php endif; ?></td>
        <td><?php if ($lessons): ?>
            <div class="crs-bar"><i style="width:<?= min(100, (int)round($w / count($lessons) * 100)) ?>%"></i></div>
            <span class="dim"><?= $w ?> з <?= count($lessons) ?> відео<?= $pr ? ' · ' . e(date('d.m', strtotime((string)$pr['last']))) : ' · ще не починав' ?></span>
          <?php endif; ?></td>
        <td style="white-space:nowrap;text-align:right">
          <?php if ($s['expires_at'] !== null): ?>
            <form method="post" action="<?= e(url('/admin/lessons')) ?>" style="display:inline"><?= Csrf::field() ?><?= $act('forever') ?>
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-line btn-xs" type="submit">Зробити безстроковим</button></form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('/admin/lessons')) ?>" style="display:inline" onsubmit="return confirm('Закрити доступ до курсу для <?= e($s['email']) ?>?')"><?= Csrf::field() ?><?= $act('revoke') ?>
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-danger btn-xs" type="submit">Закрити</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<script>
// Пошук у списку бібліотеки Bunny
(function () {
  var q = document.querySelector('[data-pick-search]');
  if (!q) return;
  q.addEventListener('input', function () {
    var s = q.value.trim().toLowerCase();
    document.querySelectorAll('.crs-pick-row').forEach(function (r) { r.hidden = s !== '' && r.dataset.title.indexOf(s) === -1; });
  });
})();
</script>
