<?php /** @var array $courses, $lessons, $bunny; @var ?array $course; @var int $cid */ ?>
<div class="admin-head"><h1 class="h-serif">Уроки курсів</h1>
  <?php if ($course): ?><a class="btn btn-line btn-sm" href="<?= e(course_url($course['slug'])) ?>" target="_blank" rel="noopener">Відкрити курс на сайті →</a><?php endif; ?></div>

<form class="admin-card" method="post" action="<?= e(url('/admin/lessons')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="bunny">
  <h2>Bunny Stream</h2>
  <p class="dim" style="margin-bottom:12px">Відео лежать у Bunny Stream. Урок — це один guid відео з бібліотеки. Щоб відео не можна було переслати чужим, увімкніть у бібліотеці <b>Security → Embed View Token Authentication</b> і вставте сюди ключ. Сайт підписує посилання на плеєр для кожного перегляду окремо.</p>
  <div class="form-grid">
    <div class="field"><label>ID бібліотеки</label><input name="library" value="<?= e($bunny['library']) ?>"></div>
    <div class="field"><label>Ключ підписаних посилань (Token Authentication Key)</label>
      <input name="token_key" type="password" autocomplete="off" placeholder="<?= $bunny['has_key'] ? 'Збережено. Введіть новий, щоб замінити' : 'Ще не задано' ?>"></div>
  </div>
  <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
  <?php if (!$bunny['has_key']): ?><span class="status-pill st-canceled" style="margin-left:10px">Ключа немає: відео без підпису</span><?php endif; ?>
</form>

<div class="admin-card">
  <h2>Курс</h2>
  <form method="get" action="<?= e(url('/admin/lessons')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <div class="field" style="min-width:300px"><label>Обрати курс</label>
      <select name="course" onchange="this.form.submit()"><?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $cid === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  </form>
  <?php if ($course): ?>
  <form method="post" action="<?= e(url('/admin/lessons')) ?>" style="display:flex;gap:14px;flex-wrap:wrap;align-items:end;margin-top:16px">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="price"><input type="hidden" name="course" value="<?= $cid ?>">
    <div class="field"><label>Ціна, ₴ (порожньо — «за запитом»)</label><input name="price" value="<?= e($course['base_price'] !== null ? (string)(float)$course['base_price'] : '') ?>"></div>
    <div class="field"><label>Доступ, днів (порожньо — безстроково)</label><input name="access_days" value="<?= e((string)($course['access_days'] ?? '')) ?>"></div>
    <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($course): ?>
<form class="admin-card" method="post" action="<?= e(url('/admin/lessons')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add"><input type="hidden" name="course" value="<?= $cid ?>">
  <h2>Додати уроки</h2>
  <p class="dim" style="margin-bottom:10px">По одному уроку в рядку: <code>guid відео | Назва уроку</code>. Порядок — як у списку.</p>
  <textarea name="lines" rows="4" style="width:100%" placeholder="37d3bbc8-3cbf-423d-aa74-e36fd2a4e4ad | Визначення медоподуктивності"></textarea>
  <div style="margin-top:10px"><button class="btn btn-gold btn-sm" type="submit">Додати</button></div>
</form>

<form class="admin-card" method="post" action="<?= e(url('/admin/lessons')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save"><input type="hidden" name="course" value="<?= $cid ?>">
  <h2>Уроки (<?= count($lessons) ?>)</h2>
  <?php if (!$lessons): ?><p class="dim">Уроків ще немає.</p><?php else: ?>
  <div style="overflow:auto"><table class="admin-table" style="width:100%">
    <tr><th style="width:70px">№</th><th>Назва</th><th>guid відео</th><th title="Показувати без купівлі">Безкоштовний</th><th></th></tr>
    <?php foreach ($lessons as $l): ?>
      <tr>
        <td><input type="number" name="l[<?= (int)$l['id'] ?>][sort]" value="<?= (int)$l['sort'] ?>" style="width:64px"></td>
        <td><input name="l[<?= (int)$l['id'] ?>][title]" value="<?= e($l['title']) ?>" style="width:100%;min-width:260px"></td>
        <td><code style="font-size:11px"><?= e($l['guid']) ?></code></td>
        <td style="text-align:center"><input type="checkbox" name="l[<?= (int)$l['id'] ?>][free]" value="1"<?= $l['free_preview'] ? ' checked' : '' ?>></td>
        <td><button class="btn btn-danger btn-xs" type="submit" form="del<?= (int)$l['id'] ?>" onclick="return confirm('Видалити урок разом з прогресом і нотатками людей?')">×</button></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <div style="margin-top:14px"><button class="btn btn-gold btn-sm" type="submit">Зберегти зміни</button></div>
  <?php endif; ?>
</form>
<?php foreach ($lessons as $l): ?>
  <form id="del<?= (int)$l['id'] ?>" method="post" action="<?= e(url('/admin/lessons')) ?>" style="display:none"><?= Csrf::field() ?>
    <input type="hidden" name="_action" value="delete"><input type="hidden" name="course" value="<?= $cid ?>"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"></form>
<?php endforeach; ?>
<?php endif; ?>
