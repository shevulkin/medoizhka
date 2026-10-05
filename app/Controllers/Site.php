<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content, Settings, Courses, Auth, JsonLd, Hub as Dir;

/**
 * Адреси старого сайту (WordPress + WooCommerce) і головна сторінка.
 *
 * Головна вимога перенесення — щоб ВСІ адреси, які вже в індексі, лишились тими
 * самими: /product/{слаг}/, /product-category/{слаг}/, /product-tag/…, /brand/…,
 * /about-us/, /pasika-medoizhka/, /volynec/ тощо. Зі скісною в кінці, як у WordPress:
 * без неї ми віддаємо 301 на адресу зі скісною, а не друге дзеркало сторінки.
 *
 * dispatch() ловить префіксні адреси (вони нічим не можуть зіткнутись із системними
 * маршрутами), fallback() — кореневі слаги сторінок і курсів, і викликається останнім,
 * коли жоден системний маршрут не підійшов.
 */
class Site
{
    public static function home(): never
    {
        // На головній — лише те, що можна купити: товар «немає в наявності» на
        // вітрині бренду виглядає як недбалість. Спершу те, що є на складі, далі «під замовлення».
        $av = Catalog::AVAIL_SQL;
        $buyable = "$av < " . Catalog::AVAIL_OUT;
        // Плитка категорії — лише там, де зараз є що купити; фото — з товару, який є
        $cats = [];
        foreach (Catalog::rootCategories() as $c) {
            $row = DB::row("SELECT COUNT(*) n, MAX(p.image) img FROM products p WHERE p.category_id = ? AND p.active = 1 AND $buyable", [$c['id']]);
            if ((int)$row['n'] === 0) continue;
            $cats[] = $c + ['n' => (int)$row['n'], 'img' => $row['img']];
        }
        // «Наш мед»: сорти від світлого до темного — ті, що є; далі решта меду з наявності.
        // Колір — умовний відтінок сорту.
        $palette = [];
        foreach ([['Акацієвий', '#f4e3a1', 'Світлий, ніжний, довго не кристалізується'], ['Ріпак', '#f2ead0', 'Кремовий, швидко кристалізується'],
                  ['Липов', '#efcd6a', 'Запашний, з м’ятною нотою'], ['Квіткови', '#e8ae45', 'Класичний смак літнього поля'],
                  ['Золотарник', '#d99426', 'Насичений, з пряною гірчинкою'], ['Мед різнотрав', '#c9832b', 'Різнотрав’я й соняшник'],
                  ['Лісови', '#9c5a1f', 'Темний, лісові квіти й ягоди']] as [$needle, $color, $note]) {
            $p = DB::row("SELECT p.*, $av AS _avail FROM products p WHERE p.active = 1 AND p.type <> 'course' AND $buyable
                          AND p.name LIKE ? AND p.name NOT LIKE '%Скраб%' ORDER BY _avail, (p.name LIKE ?) DESC, p.id LIMIT 1",
                          ['%' . $needle . '%', $needle . '%']);
            if ($p) $palette[] = ['p' => $p, 'color' => $color, 'note' => $note];
        }
        $shownIds = array_map(fn($h) => (int)$h['p']['id'], $palette);
        if (count($palette) < 4) {
            $more = DB::all("SELECT p.*, $av AS _avail FROM products p JOIN categories c ON c.id = p.category_id
                             WHERE p.active = 1 AND c.slug = 'honey-and-kompozytsiyi' AND $buyable"
                             . ($shownIds ? ' AND p.id NOT IN (' . implode(',', $shownIds) . ')' : '')
                             . ' ORDER BY _avail, p.featured DESC, p.id LIMIT ' . (4 - count($palette)));
            foreach ($more as $p) { $palette[] = ['p' => $p, 'color' => '', 'note' => '']; $shownIds[] = (int)$p['id']; }
        }
        // «Обладнання» і «Послуги» — окремі блоки нижче; послуги не залежать від складу, тож їх беремо за прапорцем service
        $beeCats = "'equipment','services','wax-exchange'";
        $forBeekeepers = DB::all("SELECT p.*, $av AS _avail FROM products p JOIN categories c ON c.id = p.category_id
                                  WHERE p.active = 1 AND p.service = 0 AND c.slug IN ($beeCats) AND $buyable
                                  ORDER BY _avail, c.sort, p.id LIMIT 4");
        $services = DB::all("SELECT p.*, $av AS _avail FROM products p
                              WHERE p.active = 1 AND p.service = 1 AND $buyable
                              ORDER BY p.featured DESC, p.id LIMIT 4");
        $blockIds = array_map(fn($r) => (int)$r['id'], array_merge($forBeekeepers, $services));
        // «Популярні» не повторюють того, що вже стоїть на головній вище й нижче:
        // ні меду з «Нашого меду», ні обладнання з окремого блоку для бджолярів.
        // Хіти першими; далі мед і апіпродукти — саме по них приходять на головну.
        $products = DB::all("SELECT p.*, $av AS _avail FROM products p JOIN categories c ON c.id = p.category_id
                             WHERE p.active = 1 AND p.type <> 'course' AND $buyable AND p.service = 0 AND (c.slug NOT IN ($beeCats) OR p.featured = 1)"
                             . (($ex = array_merge($shownIds, $blockIds)) ? ' AND p.id NOT IN (' . implode(',', $ex) . ')' : '') . "
                             ORDER BY _avail, p.featured DESC, (c.slug = 'honey-and-kompozytsiyi') DESC,
                                      (c.slug IN ('pollen','perga','propolis')) DESC,
                                      (p.image IS NULL), (p.base_price IS NULL), p.id DESC LIMIT 8");
        Catalog::preloadBrands($products);
        View::show('home/index', [
            'products' => $products,
            'cats' => $cats,
            // «Природна допомога»: теги товарів за станом здоров'я, як на нинішньому сайті
            'tags' => DB::all('SELECT name, slug FROM tags ORDER BY name'),
            'palette' => $palette,
            'for_beekeepers' => $forBeekeepers,
            'services' => $services,
            'courses' => Courses::all(),
            'practitioners' => Dir::practitioners(['limit' => 3]),
            'places' => Dir::places(['limit' => 3]),
            'stats' => [
                'products' => (int)DB::val("SELECT COUNT(*) FROM products WHERE active = 1 AND type <> 'course'"),
                'lessons' => (int)DB::val('SELECT COUNT(*) FROM course_lessons'),
                'practitioners' => (int)DB::val('SELECT COUNT(*) FROM practitioners WHERE active = 1'),
                'places' => (int)DB::val('SELECT COUNT(*) FROM places WHERE active = 1'),
            ],
            'page_title' => Settings::get('seo_title', cfg('app_name')),
            'meta_description' => Settings::get('seo_description', ''),
            'jsonld' => [['@context' => 'https://schema.org', '@type' => 'Store', 'name' => 'Медоїжка',
                'url' => abs_url('/'), 'logo' => asset_abs('img/brand/logo-medoizhka.webp')]],
        ]);
    }

    /** Розділ ще не відкрито: відвідувачу 404, персонал бачить (щоб наповнювати) */
    private static function gate(string $feature): void
    {
        if (feature($feature) || \Auth::isStaff()) return;
        http_response_code(404); View::show('errors/404');
    }

    /** 301 на ту саму адресу зі скісною в кінці (разом із параметрами запиту) */
    private static function slash(string $method): void
    {
        if ($method !== 'GET' && $method !== 'HEAD') return;
        $raw = request_path();
        if (str_ends_with($raw, '/')) return;
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        self::permanent($raw . '/' . ($qs !== '' ? '?' . $qs : ''));
    }

    public static function permanent(string $path): never
    {
        http_response_code(301);
        header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
        exit;
    }

    /** Префіксні адреси. Повертається лише тоді, коли жодна не підійшла. */
    public static function dispatch(string $method, string $path): void
    {
        // Вручну заведені редіректи мають пріоритет над усім
        $r = DB::row('SELECT id, to_path FROM redirects WHERE from_path = ?', [$path]);
        if ($r) { DB::query('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]); self::permanent($r['to_path']); }

        // Технічні адреси WordPress (/wp-content/, *-sitemap.xml, /feed/, /page/N/,
        // /wp-login.php) навмисно НЕ переносимо: нова структура своя, переносяться лише
        // адреси товарів. Усе інше старе віддає звичайну 404.
        $m = [];
        // Старі форми адрес, які вже в індексі Google: 301 на нинішні, а не 404
        //  /бренд/x → /brand/x/ ; /…/page/N і /…/feed — на саму сторінку (пагінації й стрічок у нас немає)
        if (preg_match('~^/бренд/([^/]+)$~u', $path, $m)) self::permanent('/brand/' . slug_enc($m[1]) . '/');
        if (preg_match('~^(/(?:shop|product-category/[^/]+|brand/[^/]+|product-tag/[^/]+))/page/\d+$~u', $path, $m)) self::permanent($m[1] . '/');
        if (preg_match('~^(/(?:product|product-category|brand|product-tag)/[^/]+)/feed$~u', $path, $m)) self::permanent($m[1] . '/');
        if ($path === '/shop') { self::slash($method); Shop::index(); }
        if (preg_match('~^/product-category/([^/]+)$~u', $path, $m)) { self::slash($method); Shop::index($m[1]); }
        if (preg_match('~^/product/([^/]+)$~u', $path, $m)) { self::slash($method); Shop::product($m[1]); }
        if (preg_match('~^/product-tag/([^/]+)$~u', $path, $m)) { self::slash($method); self::tag($m[1]); }
        if (preg_match('~^/brand/([^/]+)$~u', $path, $m)) { self::slash($method); self::brand($m[1]); }
        if ($path === '/courses') { self::slash($method); self::gate('courses'); Home::courses(); }

        // Сторінки bofu, які в Медоїжці мають іншу адресу: показуємо одразу потрібну
        if ($path === '/about') self::permanent('/about-us/');
        if ($path === '/delivery' || $path === '/payment') self::permanent('/delivery-and-payment/');
        if ($path === '/returns') self::permanent('/return-and-exchange/');
        if ($path === '/privacy') self::permanent('/privacy-policy/');
        if ($path === '/learning') self::permanent('/my-account/my-course/');

        // Навчання
        if ($path === '/my-account/my-course') { self::slash($method); Learn::my(); }
        if ($path === '/learn/lesson') { Learn::lesson(); }
        if ($method === 'POST' && $path === '/learn/progress') { Learn::progress(); }
        if ($method === 'POST' && $path === '/learn/watched') { Learn::watched(); }
        if ($method === 'POST' && $path === '/learn/note') { Learn::note(); }

        // Каталоги людей і місць
        if (str_starts_with($path, '/apiterapevty')) self::gate('practitioners');
        if ($path === '/apiterapevty') { self::slash($method); Directory::practitioners(); }
        if (preg_match('~^/apiterapevty/([^/]+)$~u', $path, $m)) { self::slash($method); Directory::practitioner($m[1]); }
        if ($method === 'POST' && preg_match('~^/apiterapevty/([^/]+)/zapys$~u', $path, $m)) { Directory::book('practitioner', $m[1]); }
        if ($path === '/pasiky') { self::slash($method); Directory::places(); }
        if (preg_match('~^/pasiky/([^/]+)$~u', $path, $m)) { self::slash($method); Directory::place($m[1]); }
        if ($method === 'POST' && preg_match('~^/pasiky/([^/]+)/zapys$~u', $path, $m)) { Directory::book('place', $m[1]); }
    }

    /** Кореневий слаг: сторінка зі старого сайту або курс. Кличеться останнім. */
    public static function fallback(string $method, string $path): void
    {
        if (!preg_match('~^/([^/]+)$~u', $path, $m)) return;
        $slug = $m[1];
        $page = DB::row('SELECT * FROM pages WHERE slug = ?', [$slug]);
        if ($page) { self::slash($method); self::page($page); }
        if (Courses::bySlug($slug)) { self::slash($method); Learn::course($slug); }
    }

    private static function page(array $page): never
    {
        $body = self::pageHtml((string)$page['body']);
        View::show('site/page', [
            'page' => $page, 'body' => $body,
            'page_title' => $page['seo_title'] ?: seo_title($page['title']),
            'meta_description' => seo_desc($page['seo_desc'], $body),
            'canonical' => abs_url(course_path($page['slug'])),
            'jsonld' => [JsonLd::breadcrumbs([['Головна', '/'], [$page['title'], null]])],
        ]);
    }

    /**
     * Текст сторінки, перенесеної з WordPress, — до показу. База не змінюється: правила
     * застосовуються щоразу, тож і старі, і відредаговані тексти виглядають однаково.
     */
    public static function pageHtml(string $html): string
    {
        // Скрипти зі старої верстки не переносимо: сторінка лише читається
        $html = preg_replace('~<script\b[^>]*>.*?</script>~is', '', $html);
        // {assets} — маркер, яким bin/localize-images.php замінив адреси картинок старого сайту
        $html = str_replace('{assets}/', base_url('assets/'), $html);
        // Фото, обгорнуте посиланням на «сторінку вкладення» WordPress (/med02/?v=…):
        // таких сторінок у нас немає, посилання вело на 404. Лишаємо саме фото.
        $html = preg_replace('~<a\b[^>]*>\s*(<img\b[^>]*>)\s*</a>~i', '$1', $html);
        // H1 на сторінці один — її назва; заголовки першого рівня з тексту стають другим
        $html = preg_replace('~<(/?)h1\b~i', '<$1h2', $html);
        // Наші посилання — відносні й одразу зі скісною в кінці: без зайвого 301 і
        // однаково на домені й на локальній копії
        $html = preg_replace_callback('~\bhref="(?:https?://(?:www\.)?medoizhka\.com)?(/[^"#?]*)([?#][^"]*)?"~i', function ($m) {
            $path = $m[1]; $tail = $m[2] ?? '';
            if ($path !== '/' && !str_ends_with($path, '/') && !preg_match('~\.[a-z0-9]{2,5}$~i', $path)) $path .= '/';
            return 'href="' . e(url($path)) . e(html_entity_decode($tail)) . '"';
        }, $html);
        // Карта-локатор зі стороннього сховища (800px завширшки, блокується політикою
        // безпеки) — звичайна карта Google з адресою крамниці
        $addr = Content::title('contact_address', 'м. Київ, вул. Сержа Лифаря, 4');
        $html = preg_replace('~<iframe\b[^>]*storage\.googleapis\.com[^>]*>\s*</iframe>~i',
            '<div class="map-embed"><iframe src="https://www.google.com/maps?q=' . rawurlencode('Медоїжка, ' . $addr)
            . '&amp;output=embed" title="Медоїжка на мапі" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>', $html);
        // Телефони в тексті — натискаються (поза вже наявними посиланнями й тегами)
        $parts = preg_split('~(<a\b.*?</a>|<[^>]+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) continue;
            $parts[$i] = preg_replace_callback('~(?<![\d+])(?:\+?38[\s\x{00A0}]?)?\(?0\d{2}\)?[\s\x{00A0}-]?\d{3}[\s\x{00A0}-]?\d{2}[\s\x{00A0}-]?\d{2}(?!\d)~u', function ($m) {
                $digits = preg_replace('~\D~', '', $m[0]);
                $tel = '+38' . substr($digits, -10);
                return '<a href="tel:' . $tel . '">' . $m[0] . '</a>';
            }, $part);
        }
        return implode('', $parts);
    }

    private static function tag(string $slug): never
    {
        $t = DB::row('SELECT * FROM tags WHERE slug = ?', [$slug]);
        if (!$t) { http_response_code(404); View::show('errors/404'); }
        $products = DB::all("SELECT p.*, " . Catalog::AVAIL_SQL . " AS _avail FROM products p JOIN product_tags pt ON pt.product_id = p.id
                             WHERE pt.tag_id = ? AND p.active = 1 AND p.type <> 'course' ORDER BY _avail, p.featured DESC, p.id DESC", [$t['id']]);
        Catalog::preloadBrands($products);
        self::listing($t, $products, '/product-tag/', 'Тема');
    }

    private static function brand(string $slug): never
    {
        $b = DB::row('SELECT * FROM brands WHERE slug = ?', [$slug]);
        if (!$b) { http_response_code(404); View::show('errors/404'); }
        $products = DB::all("SELECT p.*, " . Catalog::AVAIL_SQL . " AS _avail FROM products p JOIN product_brands pb ON pb.product_id = p.id
                             WHERE pb.brand_id = ? AND p.active = 1 AND p.type <> 'course' ORDER BY _avail, p.featured DESC, p.id DESC", [$b['id']]);
        Catalog::preloadBrands($products);
        self::listing($b, $products, '/brand/', 'Бренд');
    }

    private static function listing(array $row, array $products, string $prefix, string $kind): never
    {
        View::show('site/listing', [
            'row' => $row, 'products' => $products, 'kind' => $kind,
            // порожня сторінка (усі товари сховано) — не для індексу: інакше Google рахує її «ложною 404»
            'noindex' => !$products,
            'page_title' => $row['seo_title'] ?: seo_title($row['name']),
            'meta_description' => seo_desc($row['seo_desc'], $row['description'] ?? null, $row['name'] . ': продукти бджільництва від Медоїжки з доставкою по Україні.'),
            'canonical' => abs_url($prefix . slug_enc($row['slug']) . '/'),
            'jsonld' => [JsonLd::breadcrumbs([['Головна', '/'], ['Крамниця', '/shop/'], [$row['name'], null]])],
        ]);
    }
}
