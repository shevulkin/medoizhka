<?php /** @var array $rows */
$st = ['new' => 'Нова', 'confirmed' => 'Підтверджена', 'done' => 'Виконана', 'cancelled' => 'Скасована']; ?>
<div class="admin-head"><h1 class="h-serif">Бронювання</h1></div>
<p class="dim" style="margin-bottom:16px">Заявки на візити, майстер-класи та консультації. Нові приходять у Telegram, пошту й push (налаштовується в «Сповіщення»).</p>
<div class="admin-card"><div style="overflow:auto"><table class="admin-table" style="width:100%">
  <tr><th>Коли надійшла</th><th>Що</th><th>Хто</th><th>Дата / гостей</th><th>Повідомлення</th><th>Ціна й нотатка</th><th>Статус</th></tr>
  <?php foreach ($rows as $r): ?><tr>
    <td><?= e(date('d.m.Y H:i', strtotime((string)$r['created_at']))) ?></td>
    <td><?= $r['kind'] === 'place' ? 'Візит' : ($r['kind'] === 'service' ? 'Послуга' : 'Консультація') ?><br><b><?= e($r['subject']) ?></b></td>
    <td><?= e($r['name']) ?><br><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a><?= $r['email'] ? '<br>' . e($r['email']) : '' ?></td>
    <td><?= e((string)($r['on_date'] ?? '—')) ?> / <?= (int)$r['guests'] ?: '—' ?></td>
    <td style="max-width:280px"><?= e((string)$r['message']) ?></td>
    <?php /* Одна форма на рядок: статус, ціна й нотатка зберігаються разом */ ?>
    <td colspan="2"><form method="post" action="<?= e(url('/admin/bookings')) ?>" style="display:grid;grid-template-columns:minmax(190px,1.4fr) 150px;gap:8px;align-items:start"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <div style="display:grid;gap:6px">
        <label style="font-size:12px;color:var(--dim)">Узгоджена ціна, грн<input name="price" inputmode="decimal" value="<?= $r['price'] !== null && $r['price'] !== '' ? e(rtrim(rtrim(number_format((float)$r['price'], 2, '.', ''), '0'), '.')) : '' ?>" placeholder="після уточнень"></label>
        <label style="font-size:12px;color:var(--dim)">Нотатка (бачите лише ви)<textarea name="note" rows="2" maxlength="2000"><?= e((string)($r['admin_note'] ?? '')) ?></textarea></label>
      </div>
      <div style="display:grid;gap:6px">
        <label style="font-size:12px;color:var(--dim)">Статус<select name="status"><?php foreach ($st as $k => $l): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
      </div></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="dim">Заявок поки немає.</td></tr><?php endif; ?>
</table></div></div>
