<?php /** @var array $rows; @var ?array $edit */ $e = $edit ?? null; $has = is_array($e); $v = fn($k) => e((string)($e[$k] ?? '')); ?>
<div class="admin-head"><h1 class="h-serif">Пасіки й апібудиночки</h1>
  <a class="btn btn-gold btn-sm" href="<?= e(url('/admin/places?new=1')) ?>">+ Додати</a></div>

<?php if ($has): ?>
<form class="admin-card" method="post" action="<?= e(url('/admin/places')) ?>" enctype="multipart/form-data">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save"><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
  <h2><?= !empty($e['id']) ? 'Редагувати' : 'Нове місце' ?></h2>
  <div class="form-grid">
    <div class="field"><label>Назва</label><input name="name" required value="<?= $v('name') ?>"></div>
    <div class="field"><label>Тип</label><select name="kind"><?php foreach (Hub::PLACE_KINDS as $k => [$one]): ?><option value="<?= e($k) ?>"<?= ($e['kind'] ?? 'apiary') === $k ? ' selected' : '' ?>><?= e($one) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Місто</label><input name="city" value="<?= $v('city') ?>"></div>
    <div class="field"><label>Область</label><input name="region" value="<?= $v('region') ?>"></div>
    <div class="field"><label>Адреса</label><input name="address" value="<?= $v('address') ?>"></div>
    <div class="field"><label>Телефон власника</label><input name="phone" value="<?= $v('phone') ?>"></div>
    <div class="field"><label>Ціна від, ₴</label><input name="price" type="number" value="<?= $v('price') ?>"></div>
    <div class="field"><label>До ціни (за особу, за добу…)</label><input name="price_note" value="<?= $v('price_note') ?>"></div>
    <div class="field"><label>Тривалість</label><input name="duration" value="<?= $v('duration') ?>" placeholder="1,5 години"></div>
    <div class="field"><label>Місткість, осіб</label><input name="capacity" type="number" value="<?= $v('capacity') ?>"></div>
    <div class="field"><label>Порядок</label><input name="sort" type="number" value="<?= $v('sort') ?: 0 ?>"></div>
  </div>
  <div class="field"><label>Коротко (один рядок для картки)</label><input name="summary" value="<?= $v('summary') ?>"></div>
  <div class="field"><label>Опис</label><textarea name="description" rows="7"><?= $v('description') ?></textarea></div>
  <div class="field"><label>Що входить (по одному в рядку)</label><textarea name="amenities" rows="4"><?= $v('amenities') ?></textarea></div>
  <div class="field"><label>Фото</label><?php if (!empty($e['photo'])): ?><img src="<?= e(asset($e['photo'])) ?>" style="max-height:90px;margin-bottom:6px"><?php endif; ?><input type="file" name="photo" accept="image/*"></div>
  <label class="checkbox"><input type="checkbox" name="active" value="1"<?= ($e['active'] ?? 1) ? ' checked' : '' ?>> Показувати на сайті</label>
  <div style="margin-top:12px"><button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
  <?php if (!empty($e['slug'])): ?><a class="btn btn-line btn-sm" target="_blank" href="<?= e(url('/pasiky/' . slug_enc($e['slug']) . '/')) ?>">На сайті →</a><?php endif; ?></div>
</form>
<?php endif; ?>

<div class="admin-card"><h2>Усі (<?= count($rows) ?>)</h2>
  <table class="admin-table" style="width:100%"><tr><th>Назва</th><th>Тип</th><th>Місто</th><th>Статус</th><th></th></tr>
  <?php foreach ($rows as $r): ?><tr>
    <td><a href="<?= e(url('/admin/places?id=' . (int)$r['id'])) ?>"><?= e($r['name']) ?></a></td><td><?= e(Hub::kindLabel($r['kind'])) ?></td><td><?= e((string)$r['city']) ?></td>
    <td><?= $r['active'] ? 'на сайті' : 'прихований' ?></td>
    <td><form method="post" action="<?= e(url('/admin/places')) ?>" onsubmit="return confirm('Видалити?')"><?= Csrf::field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-xs">Видалити</button></form></td>
  </tr><?php endforeach; ?></table>
</div>
