<?php
/**
 * Послуги: таблиця з ціною й перемикачами + «Нова послуга».
 * @var array $rows, $cats
 */
?>
<div class="admin-head"><h1 class="h-serif">Послуги</h1></div>

<div class="admin-card crs-how">
  <h2>Як це працює</h2>
  <ol class="crs-steps">
    <li><b>Послуга</b> — те, що не лежить на складі: «Бджоли під ключ», обмін воску, консультація. Залишки до неї не застосовуються.</li>
    <li><b>Ціну</b> змінюйте прямо в таблиці нижче і натискайте «Зберегти». Порожня ціна — на сайті «Ціну уточнюйте».</li>
    <li><b>«Доступна»</b> зняли — на сайті «Тимчасово недоступна», замовити не можна, а люди можуть натиснути «Повідомити, коли відновимо». Поставили знову — їм приходить сповіщення.</li>
    <li><b>Замовляють</b> послугу за телефоном або повідомленням: кнопки «Замовити за телефоном» і «Написати нам» на її сторінці. У кошик вона не кладеться.</li>
  </ol>
</div>

<div class="admin-card">
  <h2>Нова послуга</h2>
  <form class="crs-new" method="post" action="<?= e(url('/admin/services')) ?>" style="border:0;margin:0;padding:0"><?= Csrf::field() ?>
    <input type="hidden" name="_action" value="create">
    <div class="field" style="flex:2;min-width:240px"><label>Назва</label><input name="name" required placeholder="Наприклад: Консультація бджоляра на вашій пасіці"></div>
    <div class="field" style="width:150px"><label>Ціна, ₴</label><input name="price" type="number" min="0" step="1" placeholder="за запитом"></div>
    <div class="field" style="flex:1;min-width:200px"><label>Розділ на сайті</label>
      <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-gold btn-sm" type="submit">Додати послугу</button>
  </form>
</div>

<form class="admin-card" method="post" action="<?= e(url('/admin/services')) ?>"><?= Csrf::field() ?>
  <input type="hidden" name="_action" value="save">
  <h2>Усі послуги</h2>
  <?php if (!$rows): ?>
    <p class="dim">Послуг ще немає — додайте першу вище.</p>
  <?php else: ?>
  <div style="overflow:auto"><table class="admin-table svc-table" style="width:100%">
    <tr><th>Послуга</th><th style="width:150px">Ціна, ₴</th><th title="Зніміть — і на сайті буде «Тимчасово недоступна»">Доступна</th><th title="Зніміть — і послуги не буде видно на сайті зовсім">На сайті</th><th></th></tr>
    <?php foreach ($rows as $r): $id = (int)$r['id']; ?>
      <tr id="s<?= $id ?>">
        <td><b><?= e($r['name']) ?></b><div class="dim"><?= e((string)$r['cat_name']) ?></div></td>
        <td><input name="s[<?= $id ?>][price]" type="number" min="0" step="1" value="<?= e($r['base_price'] !== null && (float)$r['base_price'] > 0 ? (string)(float)$r['base_price'] : '') ?>" placeholder="за запитом"></td>
        <td style="text-align:center"><label class="toggle" title="Доступна зараз"><input type="checkbox" name="s[<?= $id ?>][available]" value="1"<?= empty($r['paused']) ? ' checked' : '' ?>><span class="tr"></span></label></td>
        <td style="text-align:center"><label class="toggle" title="Видно на сайті"><input type="checkbox" name="s[<?= $id ?>][active]" value="1"<?= $r['active'] ? ' checked' : '' ?>><span class="tr"></span></label></td>
        <td style="white-space:nowrap;text-align:right">
          <a class="btn btn-line btn-xs" href="<?= e(url('/admin/products/' . $id)) ?>">Опис і фото</a>
          <a class="btn btn-line btn-xs" href="<?= e(product_url($r['slug'])) ?>" target="_blank" rel="noopener" title="Як бачить покупець">↗</a>
          <button class="btn btn-line btn-xs" type="submit" form="un<?= $id ?>" title="Це не послуга, а товар зі складом">Це товар</button>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
  <div style="margin-top:16px"><button class="btn btn-gold btn-sm" type="submit">Зберегти</button></div>
  <?php endif; ?>
</form>
<?php foreach ($rows as $r): ?>
  <form id="un<?= (int)$r['id'] ?>" method="post" action="<?= e(url('/admin/services')) ?>" hidden onsubmit="return confirm('Зробити це звичайним товаром? Його наявність рахуватиметься за залишком на складі.')"><?= Csrf::field() ?>
    <input type="hidden" name="_action" value="unmark"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form>
<?php endforeach; ?>
<p class="dim" style="margin-top:6px">Будь-який товар можна зробити послугою: у його картці поставте галку «Послуга (без складу)» — і він зʼявиться тут.</p>
