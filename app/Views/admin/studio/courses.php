<?php
/**
 * Відеокурси: список, «Новий відеокурс», чи видно розділ покупцям, налаштування Bunny.
 * @var array $courses, $bunny
 */
$on = feature('courses');
$dur = function (int $s): string {
    if ($s <= 0) return '';
    $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60);
    return $h ? $h . ' год ' . $m . ' хв' : $m . ' хв';
};
?>
<div class="admin-head"><h1 class="h-serif">Відеокурси</h1></div>

<div class="admin-card crs-how">
  <h2>Як це працює</h2>
  <ol class="crs-steps">
    <li><b>Створіть курс</b> — нижче, лише назва. Він зʼявиться чернеткою: покупці його ще не бачать.</li>
    <li><b>Додайте відео</b> з бібліотеки Bunny — галочками зі списку або вставивши посилання. Порядок міняється стрілками.</li>
    <li><b>Ціна, строк доступу, «Опубліковано»</b> — на сторінці курсу. Опис, програма й обкладинка — у картці товару (кнопка там же).</li>
    <li><b>Доступ відкривається сам</b>, щойно замовлення оплачене — онлайн або позначене в замовленні «Оплачено». Подарувати курс чи перенести покупців зі старого сайту — у блоці «Учні» на сторінці курсу: просто впишіть пошти.</li>
  </ol>
</div>

<div class="admin-card crs-feature">
  <div>
    <h2 style="margin:0 0 4px">Розділ «Відеокурси» на сайті</h2>
    <p class="dim" style="margin:0"><?= $on
      ? 'Увімкнено: покупці бачать опубліковані курси, сторінки курсів і «Мої відеокурси».'
      : 'Вимкнено: покупці курсів не бачать (навіть опублікованих). Вам як персоналу — видно все, можна готувати й перевіряти.' ?></p>
  </div>
  <form method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?>
    <input type="hidden" name="_action" value="feature"><input type="hidden" name="on" value="<?= $on ? '' : '1' ?>">
    <button class="btn <?= $on ? 'btn-line' : 'btn-gold' ?> btn-sm" type="submit"><?= $on ? 'Сховати з сайту' : 'Показати покупцям' ?></button>
  </form>
</div>

<div class="admin-card">
  <h2>Курси</h2>
  <form class="crs-new" method="post" action="<?= e(url('/admin/lessons')) ?>"><?= Csrf::field() ?>
    <input type="hidden" name="_action" value="create">
    <div class="field" style="flex:1;min-width:260px"><label>Новий відеокурс</label><input name="name" required placeholder="Назва, наприклад: Пасіка з нуля — базовий курс"></div>
    <button class="btn btn-gold btn-sm" type="submit">Створити курс</button>
  </form>
  <?php if (!$courses): ?>
    <p class="dim">Курсів ще немає — створіть перший вище.</p>
  <?php else: ?>
  <div style="overflow:auto"><table class="admin-table crs-list" style="width:100%">
    <tr><th>Курс</th><th>Відео</th><th>Учні</th><th>Ціна</th><th></th></tr>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><a href="<?= e(url('/admin/lessons?course=' . (int)$c['id'])) ?>"><b><?= e($c['name']) ?></b></a><br>
          <?= $c['active'] ? '<span class="status-pill st-processing">Опубліковано</span>' : '<span class="status-pill">Чернетка</span>' ?></td>
        <td><?= (int)$c['videos'] ?><?php if ($d = $dur((int)$c['secs'])): ?><div class="dim"><?= e($d) ?></div><?php endif; ?></td>
        <td><?= (int)$c['students'] ?></td>
        <td><?= $c['base_price'] !== null && (float)$c['base_price'] > 0 ? e(price_fmt($c['base_price'])) : '<span class="dim">не вказана</span>' ?></td>
        <td style="text-align:right"><a class="btn btn-line btn-xs" href="<?= e(url('/admin/lessons?course=' . (int)$c['id'])) ?>">Відкрити</a></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>

<details class="admin-card crs-bunny"<?= !$bunny['has_api'] || !$bunny['has_key'] ? ' open' : '' ?>>
  <summary><h2 style="display:inline">Налаштування Bunny Stream</h2>
    <span class="dim"> · <?= $bunny['has_api'] ? 'список відео підключено' : 'список відео не підключено' ?> · <?= $bunny['has_key'] ? 'посилання захищені' : 'посилання не захищені' ?></span></summary>
  <form method="post" action="<?= e(url('/admin/lessons')) ?>" style="margin-top:14px"><?= Csrf::field() ?>
    <input type="hidden" name="_action" value="bunny">
    <div class="form-grid">
      <div class="field"><label>ID бібліотеки</label><input name="library" value="<?= e($bunny['library']) ?>">
        <small class="dim">Bunny → Stream → ваша бібліотека: число в адресі сторінки.</small></div>
      <div class="field"><label>API-ключ бібліотеки</label>
        <input name="api_key" type="password" autocomplete="off" placeholder="<?= $bunny['has_api'] ? 'Збережено. Введіть новий, щоб замінити' : 'Ще не задано' ?>">
        <small class="dim">Bunny → Stream → бібліотека → API → API Key. З ним відео обираються галочками зі списку.</small></div>
      <div class="field"><label>Ключ підписаних посилань</label>
        <input name="token_key" type="password" autocomplete="off" placeholder="<?= $bunny['has_key'] ? 'Збережено. Введіть новий, щоб замінити' : 'Ще не задано' ?>">
        <small class="dim">Security → Embed View Token Authentication. Без нього посилання на відео можна переслати чужим.</small></div>
    </div>
    <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
  </form>
</details>
