<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? cfg('app_name')) ?></title>
<meta name="description" content="<?= e($meta_description ?? Settings::get('seo_description', '')) ?>">
<?php if (Settings::bool('seo_noindex') || !empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php /* og:url і og:site_name — те, з чого месенджери й соцмережі будують
         картку посилання. Без site_name у превʼю стоїть голий домен, без url
         вони підставляють адресу з переходу, разом із чужими мітками. */ ?>
<meta property="og:title" content="<?= e($page_title ?? cfg('app_name')) ?>">
<meta property="og:description" content="<?= e($meta_description ?? Settings::get('seo_description', '')) ?>">
<meta property="og:type" content="<?= !empty($jsonld_product) ? 'product' : 'website' ?>">
<meta property="og:site_name" content="<?= e(cfg('app_name')) ?>">
<meta property="og:locale" content="uk_UA">
<meta property="og:url" content="<?= e(current_url()) ?>">
<meta property="og:image" content="<?= e(asset_abs($og_image ?? (!empty($p) && !empty($jsonld_product) ? Catalog::photo($p) : 'img/brand/logo-medoizhka.webp'))) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#ffffff">
<?php /* Значок вкладки — окремий маленький файл, а не PWA-іконка. Та важить
         137 КБ і потрібна такою лише манифесту й apple-touch-icon; у куті
         вкладки з неї видно квадратик 16×16, за який покупець платив на кожній
         сторінці більше, ніж за всі скрипти сайту разом. */ ?>
<link rel="icon" href="<?= e(asset('img/brand/logo-medoizhka-300.webp')) ?>" type="image/webp">
<?php /* шрифти основного тексту й заголовків — завантажуємо одразу, а не після розбору CSS */ ?>
<link rel="preload" href="<?= e(asset('fonts/Manrope-400-cyr.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('fonts/Montserrat-cyr.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php /* Оголошення шрифтів вбудовано в сторінку (1 КБ): інакше браузер спершу чекає fonts.css, і лише тоді починає тягти самі шрифти */
    $fontsCss = @file_get_contents(BOFU_ROOT . '/assets/css/fonts.css'); ?>
<?php if ($fontsCss): ?><style><?= str_replace('../fonts/', asset('fonts/'), $fontsCss) ?></style>
<?php else: ?><link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>"><?php endif; ?>
<?php /* app + site + v3 + shop + medoizhka одним мініфікованим файлом (bin/build-css.php) — один запит замість пʼяти.
         Правите CSS — запустіть php bin/build-css.php і закомітьте site.min.css. */ ?>
<link rel="stylesheet" href="<?= e(asset_v('css/site.min.css')) ?>">
<link rel="canonical" href="<?= e($canonical ?? current_url()) ?>">
<?php /* Перевірка власності в Google Search Console мета-тегом — якщо задано в Налаштуваннях */ ?>
<?php if (($gsv = (string)Settings::get('google_site_verification', '')) !== ''): ?><meta name="google-site-verification" content="<?= e($gsv) ?>"><?php endif; ?>
<?= View::partial('partials/analytics') ?>
<?php
/* Розмітка для пошуковиків. Organization і WebSite — на кожній сторінці: вони
   зводять сайт, соцмережі й канал в одну сутність і дають рядок пошуку прямо
   у видачі. Решту ($jsonld) додає сторінка, яка знає, що на ній стоїть. */
echo JsonLd::tag(JsonLd::organization());
echo JsonLd::tag(JsonLd::website());
foreach (($jsonld ?? []) as $block) echo JsonLd::tag($block);
?>
</head>
<body>
<?php if (analytics_on() && ($gtmId = (string)Settings::get('gtm_id', '')) !== ''): ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e(rawurlencode($gtmId)) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>
<?php if (Settings::bool('sale_banner_active')): ?>
<div class="sale-banner"><?= e(Settings::get('sale_banner_text', '')) ?> · −<?= e(Settings::get('sale_banner_percent', '0')) ?>%</div>
<?php endif; ?>
<?= View::partial('partials/header') ?>
<?php if ($msg = flash('success')): ?><div class="flash"><div class="flash-success"><?= e($msg) ?></div></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="flash"><div class="flash-error"><?= e($msg) ?></div></div><?php endif; ?>
<main><?= $content ?></main>
<?= View::partial('partials/footer') ?>
<?= View::partial('partials/auth_modal') ?>
<div class="cart-toast" id="cartToast" role="status" aria-live="polite">
  <div class="cart-toast-icon">✓</div>
  <div class="cart-toast-body">
    <div class="cart-toast-text">Товар додано в кошик</div>
    <div class="cart-toast-actions">
      <button type="button" class="btn btn-line btn-xs" id="cartToastContinue">Продовжити покупки</button>
      <a href="<?= e(url('/checkout')) ?>" class="btn btn-gold btn-xs">Оформити замовлення</a>
    </div>
  </div>
  <button type="button" class="cart-toast-close" id="cartToastClose" aria-label="Закрити">×</button>
</div>
<button class="to-top" id="toTop" aria-label="Догори">↑</button>
<script src="<?= e(asset_v('js/app.js')) ?>" defer></script>
<script>
/* Жива сторінка: блоки плавно з'являються при прокрутці, шапка отримує тінь */
(function () {
  var bar = document.querySelector('.topbar');
  var tick = false;
  var onScroll = function () {
    if (tick) return; tick = true;
    requestAnimationFrame(function () { tick = false; if (bar) bar.classList.toggle('scrolled', window.scrollY > 8); });
  };
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
  if (!('IntersectionObserver' in window)) return;
  var els = document.querySelectorAll('.sec-head,.cat,.hn,.pc,.story-ph,.story-tx,.tile,.perk,.cr,.pp-card,.cta-in');
  var io = new IntersectionObserver(function (list) {
    list.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
  }, { rootMargin: '0px 0px -8% 0px' });
  // Спершу всі виміри, потім усі зміни: змішування читання й запису змушує браузер рахувати
  // розкладку заново для кожного елемента («примусова компоновка»)
  requestAnimationFrame(function () {
    var vh = window.innerHeight, below = [];
    for (var k = 0; k < els.length; k++) if (els[k].getBoundingClientRect().top >= vh) below.push(els[k]);   // на екрані — не ховаємо
    below.forEach(function (el, i) { el.classList.add('rv'); el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });
  });
})();
</script>
<?php if (EditMode::active()) echo View::partial('partials/edit_bar'); ?>
<?php /* Смужка каси: продавець показує покупцеві сайт, а чек іде за ним */ ?>
<?php if (Pos::active()) echo View::partial('partials/pos_bar'); ?>
</body>
</html>
