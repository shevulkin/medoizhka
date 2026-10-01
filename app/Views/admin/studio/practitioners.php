<?php /** @var array $rows; @var ?array $edit */ $e = $edit ?? null; $has = is_array($e); $v = fn($k) => e((string)($e[$k] ?? '')); ?>
<div class="admin-head"><h1 class="h-serif">Апітерапевти</h1>
  <a class="btn btn-gold btn-sm" href="<?= e(url('/admin/practitioners?new=1')) ?>">+ Додати</a></div>

<?php if ($has): ?>
<form class="admin-card" method="post" action="<?= e(url('/admin/practitioners')) ?>" enctype="multipart/form-data">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save"><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
  <h2><?= !empty($e['id']) ? 'Редагувати' : 'Новий апітерапевт' ?></h2>
  <div class="form-grid">
    <div class="field"><label>Імʼя та прізвище</label><input name="name" required value="<?= $v('name') ?>"></div>
    <div class="field"><label>Кваліфікація</label><input name="title" value="<?= $v('title') ?>" placeholder="Апітерапевт, нутриціолог"></div>
    <div class="field"><label>Місто</label><input name="city" value="<?= $v('city') ?>"></div>
    <div class="field"><label>Область</label><input name="region" value="<?= $v('region') ?>"></div>
    <div class="field"><label>Від, ₴</label><input name="price_from" type="number" value="<?= $v('price_from') ?>"></div>
    <div class="field"><label>Телефон</label><input name="phone" value="<?= $v('phone') ?>"></div>
    <div class="field"><label>Telegram</label><input name="telegram" value="<?= $v('telegram') ?>"></div>
    <div class="field"><label>Порядок</label><input name="sort" type="number" value="<?= $v('sort') ?: 0 ?>"></div>
  </div>
  <div class="field"><label>Про фахівця</label><textarea name="bio" rows="6"><?= $v('bio') ?></textarea></div>
  <div class="field"><label>Спеціалізації (по одній у рядку)</label><textarea name="specialties" rows="3"><?= $v('specialties') ?></textarea></div>
  <div class="field"><label>Послуги (по одній у рядку)</label><textarea name="services" rows="3"><?= $v('services') ?></textarea></div>
  <div class="field"><label>Фото</label><?php if (!empty($e['photo'])): ?><img src="<?= e(asset($e['photo'])) ?>" style="max-height:90px;margin-bottom:6px"><?php endif; ?><input type="file" name="photo" accept="image/*"></div>
  <div style="display:flex;gap:20px;flex-wrap:wrap;margin:10px 0">
    <label class="checkbox"><input type="checkbox" name="active" value="1"<?= ($e['active'] ?? 1) ? ' checked' : '' ?>> Показувати на сайті</label>
    <label class="checkbox"><input type="checkbox" name="online" value="1"<?= !empty($e['online']) ? ' checked' : '' ?>> Працює онлайн</label>
    <label class="checkbox"><input type="checkbox" name="verified" value="1"<?= !empty($e['verified']) ? ' checked' : '' ?>> Позначка «Перевірено» (документи бачили)</label>
  </div>
  <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
  <?php if (!empty($e['slug'])): ?><a class="btn btn-line btn-sm" target="_blank" href="<?= e(url('/apiterapevty/' . slug_enc($e['slug']) . '/')) ?>">На сайті →</a><?php endif; ?>
</form>
<?php endif; ?>

<div class="admin-card"><h2>Усі (<?= count($rows) ?>)</h2>
  <table class="admin-table" style="width:100%"><tr><th>Імʼя</th><th>Місто</th><th>Статус</th><th></th></tr>
  <?php foreach ($rows as $r): ?><tr>
    <td><a href="<?= e(url('/admin/practitioners?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></td><td><?= e((string)$r['city']) ?></td>
    <td><?= $r['active'] ? 'на сайті' : 'прихований' ?><?= $r['verified'] ? ' · перевірено' : '' ?></td>
    <td><form method="post" action="<?= e(url('/admin/practitioners')) ?>" onsubmit="return confirm('Видалити?')"><?= Csrf::field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-xs">Видалити</button></form></td>
  </tr><?php endforeach; ?></table>
</div>
