<?php /** @var array $courses, $rows */ ?>
<div class="admin-head"><h1 class="h-serif">Доступ до відеокурсів</h1></div>
<p class="dim" style="max-width:760px;margin-bottom:18px">Доступ відкривається сам після оплати курсу на сайті. Тут його видають вручну: наприклад, тим, хто купив курс на старому сайті. Людина входить на сайт тією поштою, яку ви вказали, і бачить курс у «Мої курси».</p>
<form class="admin-card" method="post" action="<?= e(url('/admin/access')) ?>" style="display:flex;gap:14px;flex-wrap:wrap;align-items:end">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="grant">
  <div class="field" style="min-width:260px"><label>Пошта</label><input type="email" name="email" required placeholder="name@example.com"></div>
  <div class="field" style="min-width:260px"><label>Курс</label><select name="course"><?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label>Днів (порожньо — безстроково)</label><input name="days" type="number" min="1"></div>
  <button class="btn btn-gold btn-sm" type="submit">Відкрити доступ</button>
</form>
<div class="admin-card"><h2>Хто має доступ (<?= count($rows) ?>)</h2>
  <div style="overflow:auto"><table class="admin-table" style="width:100%">
    <tr><th>Людина</th><th>Курс</th><th>Відкрито</th><th>До</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['uname']) ?><br><span class="dim"><?= e($r['email']) ?></span></td><td><?= e($r['course']) ?></td>
        <td><?= e(date('d.m.Y', strtotime((string)$r['granted_at']))) ?></td>
        <td><?= $r['expires_at'] ? e(date('d.m.Y', strtotime((string)$r['expires_at']))) : 'безстроково' ?></td>
        <td><form method="post" action="<?= e(url('/admin/access')) ?>" onsubmit="return confirm('Закрити доступ?')"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="revoke"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-xs">Закрити</button></form></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>
