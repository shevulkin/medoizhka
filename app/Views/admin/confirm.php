<?php /** @var string $email; @var bool $sent */ ?>
<div class="admin-head"><h1 class="h-serif">Підтвердіть дію</h1></div>
<div class="admin-card" style="max-width:560px">
  <p>Ви збираєтесь відкрити чутливий розділ (налаштування, ролі чи ключі). Для безпеки спершу підтвердіть, що це ви:
    ми надішлемо одноразовий код на пошту <b><?= e($email) ?></b>.</p>
  <?php if (!$sent): ?>
    <form method="post" action="<?= e(url('/admin/confirm')) ?>">
      <?= Csrf::field() ?><input type="hidden" name="_action" value="send">
      <button class="btn btn-gold" type="submit">Надіслати код</button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(url('/admin/confirm')) ?>" style="margin-top:14px">
      <?= Csrf::field() ?><input type="hidden" name="_action" value="verify">
      <div class="field"><label>Код з листа</label>
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required autofocus></div>
      <button class="btn btn-gold" type="submit">Підтвердити</button>
    </form>
    <form method="post" action="<?= e(url('/admin/confirm')) ?>" style="margin-top:10px">
      <?= Csrf::field() ?><input type="hidden" name="_action" value="send">
      <button class="btn btn-line btn-sm" type="submit">Надіслати код ще раз</button>
    </form>
  <?php endif; ?>
  <p class="dim" style="margin-top:14px;font-size:13px">Після підтвердження доступ до цих розділів відкритий 30 хвилин.</p>
</div>