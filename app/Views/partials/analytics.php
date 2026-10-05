<?php
/**
 * Google tag (GA4) і Tag Manager — ті самі ідентифікатори, що на старому сайті (Site Kit),
 * тож статистика продовжується без розриву. Consent Mode v2 — як там: для країн ЄЄЗ
 * згоди за замовчуванням «відхилено».
 *
 * Події електронної комерції GA4 (view_item, add_to_cart, purchase) шле функція
 * window.mzTrack — її викликають сторінка товару, кошик і сторінка «Замовлення прийнято».
 * Поки аналітика вимкнена (сайт закритий від пошуковиків), mzTrack — порожня заглушка.
 */
$tag = (string)Settings::get('google_tag_id', '');
$gtm = (string)Settings::get('gtm_id', '');
?>
<?php if (!analytics_on()): ?>
<script>window.mzTrack = function () {};</script>
<?php return; endif; ?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {"ad_personalization":"denied","ad_storage":"denied","ad_user_data":"denied","analytics_storage":"denied","functionality_storage":"denied","security_storage":"denied","personalization_storage":"denied","region":["AT","BE","BG","CH","CY","CZ","DE","DK","EE","ES","FI","FR","GB","GR","HR","HU","IE","IS","IT","LI","LT","LU","LV","MT","NL","NO","PL","PT","RO","SE","SI","SK"],"wait_for_update":500});
window.mzTrack = function (name, data) { gtag('event', name, Object.assign({ currency: 'UAH' }, data || {})); };
</script>
<?php /* Скрипти Google важать ~250 КБ і блокують потік на телефоні. Вантажимо їх після показу сторінки:
          через 2 с після load або раніше — на першу взаємодію. Події (mzTrack) не губляться: вони
          лягають у dataLayer, а скрипт їх підхопить. */ ?>
<script>
(function () {
  var done = false;
  function loadGoogle() {
    if (done) return; done = true;
<?php if ($tag !== ''): ?>
    var g = document.createElement('script'); g.async = true; g.src = 'https://www.googletagmanager.com/gtag/js?id=<?= e(rawurlencode($tag)) ?>'; document.head.appendChild(g);
    gtag('js', new Date()); gtag('config', <?= json_js($tag) ?>);
<?php endif; ?>
<?php if ($gtm !== ''): ?>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',<?= json_js($gtm) ?>);
<?php endif; ?>
  }
  ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (e) { addEventListener(e, loadGoogle, { once: true, passive: true }); });
  addEventListener('load', function () { setTimeout(loadGoogle, 2000); });
})();
</script>
<script>
/* add_to_cart: будь-яка форма «У кошик» на сайті */
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f.classList || !f.classList.contains('add-cart-form')) return;
  var id = f.querySelector('[name=product_id]'), q = f.querySelector('[name=qty]');
  mzTrack('add_to_cart', { items: [{ item_id: id ? id.value : '', item_name: f.dataset.productName || '', quantity: q ? +q.value || 1 : 1 }] });
}, true);
</script>
