<?php
/*
 * Шапка — як на medoizhka.com: офіційний логотип ліворуч, меню праворуч,
 * пошук, кабінет і кошик у помаранчевій рамці. «Мої відеокурси» — лише в тих, хто купив.
 */
$navCats = Catalog::categoryTree();
$myCourses = $auth_user ? Courses::countFor((int)$auth_user['id']) : 0;
$phone = Content::title('contact_phone');
$svcCats = array_values(array_filter($navCats, fn($c) => ($c['type'] ?? '') === 'service'));
$nav = array_values(array_filter([
    $svcCats ? ['/shop/?kind=service', 'Послуги'] : null,
    ['/beekeeping-products/', 'Види продуктів бджільництва'],
    feature('courses') ? ['/courses/', 'Відеокурси'] : null,
    feature('practitioners') ? ['/apiterapevty/', 'Апітерапевти'] : null,
    ['/pasika-medoizhka/', 'Пасіка'],
    ['/contacts/', 'Контакти'],
    ['/about-us/', 'Про нас'],
]));
$here = rtrim(request_path(), '/') . '/';
// «Послуги» підсвічуємо на вкладці каталогу й на сторінках категорій послуг (адреса з параметром, тож звичайне порівняння шляху не годиться)
$svcActive = str_starts_with($here, '/shop/') && (($_GET['kind'] ?? '') === 'service')
    || (preg_match('~^/product-category/([^/]+)/~', $here, $mm) && in_array(rawurldecode($mm[1]), array_column($svcCats, 'slug'), true));
$ico = [
    'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
    'user' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c1.3-3.6 4.1-5.4 7.5-5.4s6.2 1.8 7.5 5.4"/>',
    'bag' => '<path d="M5 7.5h14l-1.1 11.6a1 1 0 0 1-1 .9H7.1a1 1 0 0 1-1-.9z"/><path d="M9 7.5V6a3 3 0 0 1 6 0v1.5"/>',
    'play' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M10.5 9.5v5l4-2.5z"/>',
];
$svg = fn($k) => '<svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">' . $ico[$k] . '</svg>';
?>
<header class="topbar">
  <nav class="nav">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="Медоїжка — на головну">
      <img src="<?= e(asset('img/brand/logo-medoizhka-300.webp')) ?>" width="76" height="76" alt="Медоїжка">
    </a>
    <div class="nav-links">
      <span class="nav-drop" data-nav-drop>
        <a href="<?= e(url('/shop/')) ?>"<?= !$svcActive && (str_starts_with($here, '/shop/') || str_starts_with($here, '/product')) ? ' class="active"' : '' ?>>Крамниця</a>
        <?php if ($navCats): ?>
        <button type="button" class="nav-drop-btn" data-nav-drop-btn aria-controls="navShopMenu" aria-expanded="false" aria-label="Категорії">
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 6l5 5 5-5"/></svg>
        </button>
        <div class="nav-drop-menu mega" id="navShopMenu" data-nav-drop-menu hidden>
          <?php $navGoods = array_filter($navCats, fn($c) => ($c['type'] ?? 'product') === 'product'); ?>
          <div class="mega-list"><?php foreach ($navGoods as $c): ?><a href="<?= e(url('/product-category/' . $c['slug'] . '/')) ?>"><?= e($c['name']) ?></a><?php endforeach; ?></div>
          <a class="nav-drop-all" href="<?= e(url('/shop/')) ?>">Усі товари →</a>
        </div>
        <?php endif; ?>
      </span>
      <?php foreach ($nav as [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"<?= ($href === '/shop/?kind=service' ? $svcActive : str_starts_with($here, $href)) ? ' class="active"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="nav-side">
      <form class="nav-search" method="get" action="<?= e(url('/shop/')) ?>" role="search">
        <label class="sr-only" for="navSearch">Пошук</label>
        <input type="search" id="navSearch" name="q" placeholder="Пошук" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
        <button type="submit" aria-label="Знайти"><?= $svg('search') ?></button>
      </form>
      <?php if ($auth_user): ?>
        <?php if ($myCourses > 0): ?><a class="ico-link" href="<?= e(url('/my-account/my-course/')) ?>" title="Мої відеокурси"><?= $svg('play') ?></a><?php endif; ?>
        <?php if (Auth::isStaff()): ?><a class="ico-link staff" href="<?= e(url('/admin')) ?>" title="Адмінпанель"><span>Адмінпанель</span></a><?php endif; ?>
        <a class="ico-link" href="<?= e(url('/profile')) ?>" title="Мій кабінет"><?= $svg('user') ?></a>
      <?php else: ?>
        <a class="ico-link" href="#" id="loginBtn" title="Увійти"><?= $svg('user') ?></a>
      <?php endif; ?>
      <a class="ico-link cart-link" href="<?= e(url('/cart')) ?>" title="Кошик"><?= $svg('bag') ?>
        <span class="cart-badge"><?= (int)$cart_count ?></span>
      </a>
      <button class="mobile-menu-btn" id="mobileBtn" aria-label="Меню"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    </div>
  </nav>
  <div class="mobile-menu" id="mobileMenu">
    <form class="nav-search" method="get" action="<?= e(url('/shop/')) ?>" role="search">
      <label class="sr-only" for="navSearchMobile">Пошук товарів</label>
      <input type="search" id="navSearchMobile" name="q" placeholder="Пошук товарів" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
      <button type="submit" aria-label="Знайти"><?= $svg('search') ?></button>
    </form>
    <?php /* Крамниця: посилання в каталог + стрілка, що розкриває категорії товарів з підкатегоріями (app.js, data-nav-drop-btn). «Послуги» — окремий пункт нижче. */
    $mGoods = array_values(array_filter($navCats, fn($c) => ($c['type'] ?? 'product') === 'product')); ?>
    <div class="m-drop" data-nav-drop>
      <a href="<?= e(url('/shop/')) ?>">Крамниця</a>
      <?php if ($mGoods): ?><button type="button" class="nav-drop-btn" data-nav-drop-btn aria-controls="mNavShop" aria-expanded="false" aria-label="Категорії"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 6l5 5 5-5"/></svg></button><?php endif; ?>
    </div>
    <?php if ($mGoods): ?>
    <div class="m-drop-menu" id="mNavShop" data-nav-drop-menu hidden>
      <?php foreach ($mGoods as $c): ?>
        <a href="<?= e(url('/product-category/' . $c['slug'] . '/')) ?>"><?= e($c['name']) ?></a>
        <?php foreach ($c['children'] ?? [] as $k): ?><a class="nav-drop-sub" href="<?= e(url('/product-category/' . $k['slug'] . '/')) ?>"><?= e($k['name']) ?></a><?php endforeach; ?>
      <?php endforeach; ?>
      <a class="nav-drop-all" href="<?= e(url('/shop/')) ?>">Усі товари →</a>
    </div>
    <?php endif; ?>
    <?php foreach ($nav as [$href, $label]): ?><a href="<?= e(url($href)) ?>"><?= e($label) ?></a><?php endforeach; ?>
    <?php if ($myCourses > 0): ?><a href="<?= e(url('/my-account/my-course/')) ?>">Мої відеокурси</a><?php endif; ?>
    <?php if ($auth_user): ?>
      <?php if (Auth::isStaff()): ?><a href="<?= e(url('/admin')) ?>">Адмінпанель</a><?php endif; ?>
      <a href="<?= e(url('/orders')) ?>">Мої замовлення</a>
      <form method="post" action="<?= e(url('/logout')) ?>"><?= Csrf::field() ?><button class="btn btn-line btn-sm" type="submit" style="margin-top:14px">Вийти</button></form>
    <?php else: ?>
      <a href="#" class="m-login" id="loginBtnMobile" data-auth-open>Увійти</a>
    <?php endif; ?>
    <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"><?= e($phone) ?></a><?php endif; ?>
  </div>
</header>
