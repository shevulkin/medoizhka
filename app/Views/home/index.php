<?php
/**
 * Головна Медоїжки — структура medoizhka.com («Медоїжка — смак справжньої природи»,
 * категорії-плитки, «Природна допомога»), виконана якісніше: hero з фото, переваги,
 * товари, історія пасіки, банер відвідування, контакти.
 * @var array $products, $cats, $palette, $for_beekeepers, $tags
 */
$img = fn(string $f) => asset('img/home/' . pathinfo($f, PATHINFO_FILENAME) . '.webp');
$ico = [
    'hive' => '<path d="M12 3l7 4v6c0 4-3 7-7 8-4-1-7-4-7-8V7z"/><path d="M9 12l2 2 4-4"/>',
    'drop' => '<path d="M12 3c3 4 6 7 6 10.5A6 6 0 0 1 6 13.5C6 10 9 7 12 3z"/>',
    'truck' => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="17" cy="17.5" r="1.6"/>',
    'shop' => '<path d="M4 10l8-6 8 6v10H4z"/><path d="M10 20v-6h4v6"/>',
];
$svg = fn($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">' . $ico[$k] . '</svg>';
$phone = Content::title('contact_phone'); $email = Content::title('contact_email');
$addr = Content::title('contact_address'); $hours = Content::title('contact_hours');
$honey = array_map(fn($h) => $h['p'], $palette);
?>
<section class="mh-hero-x">
  <div class="in">
    <div class="txt">
      <div class="kicker">Родинна пасіка на Бориспільщині</div>
      <h1>Медоїжка — <b>смак справжньої</b> природи</h1>
      <p class="lead">Вітаємо у світі продуктів бджільництва! Натуральний мед, прополіс, пилок і віск з власної пасіки — без цукру й домішок.</p>
      <div class="trust">
        <div><?= $svg('hive') ?>Власна пасіка</div>
        <div><?= $svg('drop') ?>Без цукру й домішок</div>
        <div><?= $svg('truck') ?>Нова Пошта по Україні</div>
      </div>
      <div class="hero-actions">
        <a class="btn btn-gold" href="<?= e(url('/shop/')) ?>">Завітати до крамниці</a>
        <a class="btn btn-line" href="<?= e(url('/beekeeping-products/')) ?>">Види продуктів</a>
      </div>
    </div>
    <div class="pic"><img src="<?= e($img('flower-honey.webp')) ?>" srcset="<?= e($img('flower-honey-480.webp')) ?> 480w, <?= e($img('flower-honey.webp')) ?> 800w" sizes="(max-width:1100px) 100vw, 50vw" alt="Квітковий мед Медоїжка" width="800" height="800" fetchpriority="high" decoding="async"></div>
  </div>
</section>

<?php if ($cats): ?>
<section class="sec">
  <div class="wrap">
    <div class="sec-head"><div><div class="kicker">Каталог</div><h2>Категорії</h2></div><a class="link-arrow" href="<?= e(url('/shop/')) ?>">Уся крамниця →</a></div>
    <div class="mz-cats">
      <?php foreach ($cats as $c): $pic = $c['image'] ?: $c['img']; ?>
        <a class="mz-cat" href="<?= e(url(shop_path($c['slug']))) ?>">
          <?php if ($pic): ?><img src="<?= e(asset(Images::displayThumb($pic))) ?>" alt="<?= e($c['name']) ?>" loading="lazy"><?php endif; ?>
          <span><?= e($c['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec bg-cream">
  <div class="wrap">
    <div class="benefits">
      <div><?= $svg('hive') ?><b>Власна пасіка</b><span>Екологічно чисті угіддя Бориспільщини</span></div>
      <div><?= $svg('drop') ?><b>Без цукру й домішок</b><span>Жодної хімії під час медозбору</span></div>
      <div><?= $svg('truck') ?><b>Доставка Новою Поштою</b><span>По всій Україні</span></div>
      <div><?= $svg('shop') ?><b>Крамниця в Києві</b><span>Самовивіз і дегустація</span></div>
    </div>
  </div>
</section>

<?php /* Одна картка в ряду на чотири виглядає як порожня вітрина — блок лише з двох сортів */ ?>
<?php if (count($honey) >= 2): ?>
<section class="sec">
  <div class="wrap">
    <div class="sec-head"><div><div class="kicker">Сорти меду</div><h2>Наш мед</h2></div><a class="link-arrow" href="<?= e(url('/product-category/honey-and-kompozytsiyi/')) ?>">Увесь мед →</a></div>
    <div class="pgrid"><?php foreach (array_slice($honey, 0, 4) as $prod) echo View::partial('partials/m_card', ['prod' => $prod]); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="sec bg-cream">
  <div class="wrap mz-story">
    <div class="rhomb"><img src="<?= e($img('fmily.png')) ?>" srcset="<?= e($img('fmily-540.png')) ?> 540w, <?= e($img('fmily.png')) ?> 900w" sizes="(max-width:760px) 92vw, 540px" alt="Пасіка Медоїжки" width="900" height="900" loading="lazy" decoding="async"></div>
    <div>
      <div class="kicker">Про нас</div>
      <h2>Від бджоли <b>до баночки</b></h2>
      <p>Медоїжка — це більше, ніж медова крамниця. Це родинна справа, що виросла з любові до бджіл, природи та українських традицій бджільництва.</p>
      <ul class="mz-checks">
        <li>Пасіка в екологічно чистих районах Бориспільщини</li>
        <li>Самі доглядаємо кожен вулик і обираємо найкращі соти</li>
        <li>Без хімії під час медозбору, без цукру й домішок</li>
      </ul>
      <div class="hero-actions"><a class="btn btn-gold" href="<?= e(url('/about-us/')) ?>">Більше про нас</a><a class="btn btn-line" href="<?= e(url('/pasika-medoizhka/')) ?>">Пасіка Медоїжка</a></div>
    </div>
  </div>
</section>

<?php if ($products): ?>
<section class="sec">
  <div class="wrap">
    <div class="sec-head"><div><div class="kicker">Крамниця</div><h2>Популярні товари</h2></div><a class="link-arrow" href="<?= e(url('/shop/')) ?>">Усі товари →</a></div>
    <div class="pgrid"><?php foreach (array_slice($products, 0, 8) as $prod) echo View::partial('partials/m_card', ['prod' => $prod]); ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($tags): ?>
<section class="sec bg-cream">
  <div class="wrap mz-help">
    <div class="mz-help-ph"><img src="<?= e(asset((string)(DB::val("SELECT image FROM categories WHERE slug = 'pollen'") ?: 'img/home/flower-honey.webp'))) ?>" alt="Бджолиний пилок" loading="lazy"></div>
    <div>
      <div class="kicker">Природна допомога</div>
      <h2>Продукти бджільництва <b>для здоров’я</b></h2>
      <p class="lead" style="margin-top:12px">Оберіть тему — покажемо продукти, які традиційно використовують для підтримки організму. Перед застосуванням порадьтеся з лікарем.</p>
      <div class="mz-tags"><?php foreach ($tags as $t): ?><a href="<?= e(url('/product-tag/' . slug_enc($t['slug']) . '/')) ?>"><?= e($t['name']) ?></a><?php endforeach; ?></div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="wrap">
    <?php $hasPlaces = Hub::hasPlaces(); /* поки місць для запису немає — ведемо на сторінку нашої пасіки */ ?>
    <a class="mz-visit" href="<?= e(url($hasPlaces ? '/pasiky/' : '/pasika-medoizhka/')) ?>">
      <img src="<?= e($img('beestory.png')) ?>" srcset="<?= e($img('beestory-640.png')) ?> 640w, <?= e($img('beestory.png')) ?> 1000w" sizes="(max-width:760px) 92vw, 1200px" alt="" width="1000" height="1000" loading="lazy" decoding="async">
      <div class="mz-visit-in">
        <div class="kicker">Завітайте до нас</div>
        <h2><?= $hasPlaces ? 'Пасіки й апібудиночки' : 'Пасіка Медоїжка' ?></h2>
        <p>Екскурсія пасікою, дегустація меду просто з сот і відпочинок серед квітучих полів.</p>
        <span class="btn btn-gold"><?= $hasPlaces ? 'Обрати місце' : 'Про пасіку' ?></span>
      </div>
    </a>
  </div>
</section>

<?php if ($for_beekeepers): ?>
<section class="sec" style="padding-top:0">
  <div class="wrap">
    <div class="sec-head"><div><div class="kicker">Для бджолярів</div><h2>Обладнання та послуги</h2></div><a class="link-arrow" href="<?= e(url('/product-category/equipment/')) ?>">Усе обладнання →</a></div>
    <div class="pgrid"><?php foreach ($for_beekeepers as $prod) echo View::partial('partials/m_card', ['prod' => $prod]); ?></div>
  </div>
</section>
<?php endif; ?>

<section class="sec bg-cream">
  <div class="wrap">
    <div class="sec-head"><div><div class="kicker">Контакти</div><h2>Ми завжди на зв’язку</h2></div></div>
    <div class="mz-contacts">
      <?php if ($phone !== ''): ?><div class="mz-contact"><small>Телефон</small><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"><?= e($phone) ?></a></div><?php endif; ?>
      <?php if ($email !== ''): ?><div class="mz-contact"><small>Пошта</small><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div><?php endif; ?>
      <?php if ($addr !== ''): ?><div class="mz-contact"><small>Медова крамниця</small><b><?= e($addr) ?></b></div><?php endif; ?>
      <?php if ($hours !== ''): ?><div class="mz-contact"><small>Графік</small><b><?= e($hours) ?></b></div><?php endif; ?>
    </div>
  </div>
</section>
