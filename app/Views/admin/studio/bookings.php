<?php /** @var array $rows */
$st = ['new' => 'Нова', 'confirmed' => 'Підтверджена', 'done' => 'Виконана', 'cancelled' => 'Скасована']; ?>
<div class="admin-head"><h1 class="h-serif">Заявки</h1></div>
<p class="dim" style="margin-bottom:16px">Заявки на візити, майстер-класи та консультації. Нові приходять у Telegram, пошту й push (налаштовується в «Сповіщення»).</p>
<div class="admin-card"><div style="overflow:auto"><table class="admin-table" style="width:100%">
  <tr><th>Коли надійшла</th><th>Що</th><th>Хто</th><th>Дата / гостей</th><th>Повідомлення</th><th>Статус</th></tr>
  <?php foreach ($rows as $r): ?><tr>
    <td><?= e(date('d.m.Y H:i', strtotime((string)$r['created_at']))) ?></td>
    <td><?= $r['kind'] === 'place' ? 'Візит' : 'Консультація' ?><br><b><?= e($r['subject']) ?></b></td>
    <td><?= e($r['name']) ?><br><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a><?= $r['email'] ? '<br>' . e($r['email']) : '' ?></td>
    <td><?= e((string)($r['on_date'] ?? '—')) ?> / <?= (int)$r['guests'] ?: '—' ?></td>
    <td style="max-width:280px"><?= e((string)$r['message']) ?></td>
    <td><form method="post" action="<?= e(url('/admin/bookings')) ?>"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <select name="status" onchange="this.form.submit()"><?php foreach ($st as $k => $l): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></form></td>
  </tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="dim">Заявок поки немає.</td></tr><?php endif; ?>
</table></div></div>
