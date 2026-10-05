<?php
/*
 * Підвал: світлий, пʼять рівних колонок — бренд і соцмережі, три переліки посилань, контакти.
 * На телефоні — бренд, контакти, далі посилання у дві колонки. Контакти показуються лише
 * заповнені — вигаданого номера тут не буде.
 */
$phone = Content::title('contact_phone');
$email = Content::title('contact_email');
$hours = Content::title('contact_hours');
$addr = Content::title('contact_address');
$entity = Content::title('legal_entity');
$cols = [
    'Крамниця' => [['/shop/', 'Весь каталог'], ['/product-category/honey-and-kompozytsiyi/', 'Мед і композиції'], ['/product-category/propolis/', 'Прополіс'], ['/product-category/kosmetsevtyka/', 'Натуральна косметика'], ['/product-category/equipment/', 'Обладнання']],
    // «Пасіки й апібудиночки» — лише коли там є хоч одне місце: порожній розділ гірший за відсутній
    'Медоїжка' => [['/about-us/', 'Про нас'], ['/pasika-medoizhka/', 'Пасіка Медоїжка'], ['/beekeeping-products/', 'Види продуктів бджільництва'],
                   ...(Hub::hasPlaces() ? [['/pasiky/', 'Пасіки й апібудиночки']] : []), ['/contacts/', 'Контакти']],
    'Покупцеві' => [['/delivery-and-payment/', 'Доставка та оплата'], ['/return-and-exchange/', 'Обмін і повернення'], ['/profile', 'Мій кабінет'], ['/cart', 'Кошик']],
];
$icons = [
    'facebook' => ['Facebook', '<path d="M14 8h2.5V4.5H14c-2.2 0-4 1.8-4 4V11H7.5v3.5H10V21h3.5v-6.5H16l.5-3.5h-3V8.5c0-.3.2-.5.5-.5z"/>'],
    'instagram' => ['Instagram', '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>'],
    'tiktok' => ['TikTok', '<path d="M14 3.5v11.2a3.3 3.3 0 1 1-3.3-3.3"/><path d="M14 3.5c.4 2.6 2.2 4.4 5 4.6"/>'],
];
$ico = fn(string $p) => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
?>
<footer class="mz-footer">
  <div class="wrap mz-foot">
    <div class="mz-foot-brand">
      <a class="mz-foot-logo" href="<?= e(url('/')) ?>" aria-label="Медоїжка — на головну"><img src="<?= e(asset('img/brand/logo-medoizhka-300.webp')) ?>" width="76" height="76" alt="Медоїжка" loading="lazy"></a>
      <p>Натуральний мед і продукти бджільництва з родинної пасіки на Бориспільщині.</p>
      <div class="mz-foot-social">
        <?php foreach ($icons as $k => [$label, $path]):
          $href = Content::title('social_' . $k); if ($href === '') continue; ?>
          <a href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= $ico($path) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mz-foot-contacts">
      <h3>Контакти</h3>
      <?php if ($phone !== ''): ?><a class="mz-foot-phone" href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"<?= edit_mark('contact_phone', 'title') ?>><?= e($phone) ?></a><?php endif; ?>
      <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"<?= edit_mark('contact_email', 'title') ?>><?= e($email) ?></a><?php endif; ?>
      <?php if ($hours !== ''): ?><span<?= edit_mark('contact_hours', 'title') ?>><?= e($hours) ?></span><?php endif; ?>
      <?php if ($addr !== ''): ?><span><?= e($addr) ?></span><?php endif; ?>
    </div>

    <?php foreach ($cols as $title => $links): ?>
      <nav class="mz-foot-nav" aria-label="<?= e($title) ?>">
        <h3><?= e($title) ?></h3>
        <?php foreach ($links as [$href, $label]): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
  </div>
  <div class="wrap mz-foot-copy">
    <?php /* Є реквізити — назва продавця (ФОП) і окремо торгова марка; немає — лише марка */ ?>
    <span>© <?= date('Y') ?> <?= $entity !== '' ? e(rtrim($entity, '. ')) . '. Медоїжка®' : 'Медоїжка®' ?> — зареєстрована торгова марка. Фото й тексти сайту захищені авторським правом.</span>
    <span><a href="<?= e(url('/offer')) ?>">Публічна оферта</a><a href="<?= e(url('/privacy-policy/')) ?>">Політика конфіденційності</a></span>
  </div>
</footer>
