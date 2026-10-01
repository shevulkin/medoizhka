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
        // Хіти: спершу позначені «хіт», далі ті, що мають і фото, і ціну — порожня картка на головній гірша за відсутню
        // Мед і апіпродукти — першими: саме по них приходять на головну
        $products = DB::all("SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id
                             WHERE p.active = 1 AND p.type <> 'course'
                             ORDER BY p.featured DESC, (c.slug = 'honey-and-kompozytsiyi') DESC,
                                      (c.slug IN ('pollen','perga','propolis')) DESC,
                                      (p.image IS NULL), (p.base_price IS NULL), p.id DESC LIMIT 8");
        Catalog::preloadBrands($products);
        // Категорії з фото: беремо фото першого товару категорії
        $cats = [];
        foreach (Catalog::rootCategories() as $c) {
            $row = DB::row("SELECT COUNT(*) n, MAX(image) img FROM products WHERE category_id = ? AND active = 1", [$c['id']]);
            if ((int)$row['n'] === 0) continue;
            $cats[] = $c + ['n' => (int)$row['n'], 'img' => $row['img']];
        }
        // Медова палітра: сорти від світлого до темного. Колір — умовний відтінок сорту.
        $palette = [];
        foreach ([['Акацієвий', '#f4e3a1', 'Світлий, ніжний, довго не кристалізується'], ['Ріпак', '#f2ead0', 'Кремовий, швидко кристалізується'],
                  ['Липов', '#efcd6a', 'Запашний, з м’ятною нотою'], ['Квіткови', '#e8ae45', 'Класичний смак літнього поля'],
                  ['Золотарник', '#d99426', 'Насичений, з пряною гірчинкою'], ['Мед різнотрав', '#c9832b', 'Різнотрав’я й соняшник'],
                  ['Лісови', '#9c5a1f', 'Темний, лісові квіти й ягоди']] as [$needle, $color, $note]) {
            $p = DB::row("SELECT * FROM products WHERE active = 1 AND type <> 'course' AND name LIKE ? AND name NOT LIKE '%Скраб%' ORDER BY (name LIKE ?) DESC, id LIMIT 1", ['%' . $needle . '%', $needle . '%']);
            if ($p) $palette[] = ['p' => $p, 'color' => $color, 'note' => $note];
        }
        $forBeekeepers = DB::all("SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id
                                  WHERE p.active = 1 AND c.slug IN ('equipment','services','wax-exchange') ORDER BY c.sort, p.id LIMIT 4");
        View::show('home/index', [
            'products' => $products,
            'cats' => $cats,
            // «Природна допомога»: теги товарів за станом здоров'я, як на нинішньому сайті
            'tags' => DB::all('SELECT name, slug FROM tags ORDER BY name'),
            'palette' => $palette,
            'for_beekeepers' => $forBeekeepers,
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
        // Скрипти зі старої верстки не переносимо: сторінка лише читається
        $body = preg_replace('~<script\b[^>]*>.*?</script>~is', '', (string)$page['body']);
        // {assets} — маркер, яким bin/localize-images.php замінив адреси картинок старого сайту
        $body = str_replace('{assets}/', base_url('assets/'), $body);
        View::show('site/page', [
            'page' => $page, 'body' => $body,
            'page_title' => $page['seo_title'] ?: seo_title($page['title']),
            'meta_description' => seo_desc($page['seo_desc'], $body),
            'canonical' => abs_url(course_path($page['slug'])),
            'jsonld' => [JsonLd::breadcrumbs([['Головна', '/'], [$page['title'], null]])],
        ]);
    }

    private static function tag(string $slug): never
    {
        $t = DB::row('SELECT * FROM tags WHERE slug = ?', [$slug]);
        if (!$t) { http_response_code(404); View::show('errors/404'); }
        $products = DB::all("SELECT p.* FROM products p JOIN product_tags pt ON pt.product_id = p.id
                             WHERE pt.tag_id = ? AND p.active = 1 ORDER BY p.id DESC", [$t['id']]);
        Catalog::preloadBrands($products);
        self::listing($t, $products, '/product-tag/', 'Тема');
    }

    private static function brand(string $slug): never
    {
        $b = DB::row('SELECT * FROM brands WHERE slug = ?', [$slug]);
        if (!$b) { http_response_code(404); View::show('errors/404'); }
        $products = DB::all("SELECT p.* FROM products p JOIN product_brands pb ON pb.product_id = p.id
                             WHERE pb.brand_id = ? AND p.active = 1 ORDER BY p.id DESC", [$b['id']]);
        Catalog::preloadBrands($products);
        self::listing($b, $products, '/brand/', 'Бренд');
    }

    private static function listing(array $row, array $products, string $prefix, string $kind): never
    {
        View::show('site/listing', [
            'row' => $row, 'products' => $products, 'kind' => $kind,
            'page_title' => $row['seo_title'] ?: seo_title($row['name']),
            'meta_description' => seo_desc($row['seo_desc'], $row['description'] ?? null, $row['name'] . ': продукти бджільництва від Медоїжки з доставкою по Україні.'),
            'canonical' => abs_url($prefix . slug_enc($row['slug']) . '/'),
            'jsonld' => [JsonLd::breadcrumbs([['Головна', '/'], ['Крамниця', '/shop/'], [$row['name'], null]])],
        ]);
    }
}
