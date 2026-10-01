<?php /** @var string $action, $title; @var bool $sent; @var bool $withGuests */ ?>
<form class="form-card" id="zapys" method="post" action="<?= e($action) ?>">
  <?= Csrf::field() ?>
  <h3 style="font-size:22px"><?= e($title) ?></h3>
  <?php if ($sent): ?><div class="ok-box">Дякуємо! Заявку отримано, з вами зв’яжуться найближчим часом.</div><?php endif; ?>
  <label>Ваше імʼя<input name="name" required maxlength="120" autocomplete="name"></label>
  <label>Телефон<input name="phone" required type="tel" maxlength="40" autocomplete="tel" placeholder="+380…"></label>
  <div class="form-2">
    <label>Бажана дата<input name="on_date" type="date" min="<?= date('Y-m-d') ?>"></label>
    <?php if (!empty($withGuests)): ?><label>Гостей<input name="guests" type="number" min="1" max="99" value="2"></label><?php endif; ?>
  </div>
  <label>Повідомлення<textarea name="message" rows="3" maxlength="2000" placeholder="Що важливо знати?"></textarea></label>
  <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
  <button class="btn btn-gold" type="submit">Надіслати заявку</button>
  <p class="dim" style="font-size:12.5px">Оплата наперед не потрібна. Умови та вартість уточнить власник.</p>
</form>
