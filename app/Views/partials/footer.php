<?php
/* Підвал: чорний, з ромбовою ґраткою. Контакти показуються лише заповнені — вигаданого номера тут не буде. */
$phone = Content::title('contact_phone');
$email = Content::title('contact_email');
$hours = Content::title('contact_hours');
$entity = Content::title('legal_entity');
$cols = [
    'Крамниця' => [['/shop/', 'Весь каталог'], ['/product-category/honey-and-kompozytsiyi/', 'Мед і композиції'], ['/product-category/propolis/', 'Прополіс'], ['/product-category/kosmetsevtyka/', 'Натуральна косметика'], ['/product-category/equipment/', 'Обладнання']],
    // «Пасіки й апібудиночки» — лише коли там є хоч одне місце: порожній розділ гірший за відсутній
    'Медоїжка' => [...(Hub::hasPlaces() ? [['/pasiky/', 'Пасіки й апібудиночки']] : []), ['/pasika-medoizhka/', 'Пасіка Медоїжка'], ['/beekeeping-products/', 'Продукти бджільництва']],
    'Покупцеві' => [['/delivery-and-payment/', 'Доставка та оплата'], ['/return-and-exchange/', 'Обмін і повернення'], ['/contacts/', 'Контакти'], ['/about-us/', 'Про нас'], ['/privacy-policy/', 'Політика конфіденційності']],
];
?>
<footer class="ink-block">
  <div class="wrap mh-foot">
    <div class="mh-foot-brand">
      <img src="<?= e(asset('img/brand/logo-medoizhka-300.webp')) ?>" width="120" height="120" alt="Медоїжка" loading="lazy">
      <p>Натуральний мед і продукти бджільництва з родинної пасіки на Бориспільщині.</p>
      <?php if ($phone !== ''): ?><a class="foot-phone" href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"<?= edit_mark('contact_phone', 'title') ?>><?= e($phone) ?></a><?php endif; ?>
      <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"<?= edit_mark('contact_email', 'title') ?>><?= e($email) ?></a><?php endif; ?>
      <?php if ($hours !== ''): ?><span class="dim"<?= edit_mark('contact_hours', 'title') ?>><?= e($hours) ?></span><?php endif; ?>
      <?php if (($addr = Content::title('contact_address')) !== ''): ?><span class="dim"><?= e($addr) ?></span><?php endif; ?>
      <div class="foot-social">
        <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok'] as $k => $label):
          $href = Content::title('social_' . $k); if ($href === '') continue; ?>
          <a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php foreach ($cols as $title => $links): ?>
      <nav aria-label="<?= e($title) ?>">
        <h3><?= e($title) ?></h3>
        <?php foreach ($links as [$href, $label]): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
  </div>
  <div class="wrap mh-foot-copy">
    <span>© <?= date('Y') ?> <?= $entity !== '' ? e($entity) : 'Медоїжка' ?>. Медоїжка® — зареєстрована торгова марка.</span>
    <span><a href="<?= e(url('/offer')) ?>">Публічна оферта</a> · <a href="<?= e(url('/privacy-policy/')) ?>">Конфіденційність</a></span>
  </div>
</footer>
