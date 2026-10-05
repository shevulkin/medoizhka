<?php
declare(strict_types=1);

namespace Controllers;

use DB, WebPush, Settings, Hub as Dir;

class Seo
{
    public static function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (Settings::bool('seo_noindex')) {
            echo "User-agent: *\nDisallow: /\n";
            exit;
        }
        $host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        // Службові сторінки й пошук — не для індексу; старі WP-адреси кабінету теж
        echo "User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /profile\n"
           . "Disallow: /orders\nDisallow: /learn/\nDisallow: /my-account/\nDisallow: /bargain\nDisallow: /*?q=\n"
           . "Disallow: /*?sort=\n";
        echo "Sitemap: $scheme://$host" . base_url('/sitemap.xml') . "\n";
        exit;
    }

    /** Старий адрес sitemap_index.xml: індекс із одним записом — новий /sitemap.xml */
    public static function sitemapIndex(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        $host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "
"
           . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>'
           . htmlspecialchars($scheme . '://' . $host . base_url('/sitemap.xml')) . '</loc></sitemap></sitemapindex>';
        exit;
    }

    public static function sitemap(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        if (Settings::bool('seo_noindex')) {
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
               . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
            exit;
        }
        $host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $abs = fn(string $p) => $scheme . '://' . $host . base_url($p);
        // Правові сторінки й «Де нас знайти» теж у карті: за запитами «доставка
        // мед», «повернення» і назвою міста люди приходять саме на них, а
        // Google Merchant Center вимагає, щоб умови доставки й повернення були
        // доступні окремими адресами.
        // Адреси — у тому вигляді, в якому їх уже проіндексовано зі старого сайту
        // (зі скісною в кінці, кирилиця відсотковими кодами): див. Controllers\Site.
        // Розділи, яких зараз немає на сайті (порожні «Пасіки», перевірка дипломів без
        // жодного диплома), у карту не потрапляють: пошуковику нема що там знайти.
        $urls = [['/', '1.0'], ['/shop/', '0.9'], ['/offer', '0.3']];
        if (Dir::hasPlaces()) $urls[] = ['/pasiky/', '0.8'];
        if (Home::diplomasEnabled()) $urls[] = ['/diploma', '0.4'];
        foreach (DB::all('SELECT slug, updated_at FROM pages') as $pg) {
            $urls[] = [course_path($pg['slug']), '0.6', $pg['updated_at']];
        }
        // Усі товари, і вимкнені теж: їхні сторінки відкриваються за прямим посиланням
        // («Немає в наявності») і мають лишатися в пошуку — див. Shop::product.
        foreach (DB::all("SELECT slug, updated_at FROM products WHERE type <> 'course'") as $p) {
            $urls[] = [product_path($p['slug']), '0.8', $p['updated_at']];
        }
        // Категорії — лише ті, де є що показати
        foreach (DB::all("SELECT c.slug FROM categories c WHERE c.active = 1 AND c.type <> 'course'
                          AND EXISTS (SELECT 1 FROM products p WHERE p.category_id = c.id AND p.active = 1)") as $c) {
            $urls[] = [shop_path($c['slug']), '0.7'];
        }
        foreach (DB::all('SELECT slug FROM tags') as $t) $urls[] = ['/product-tag/' . slug_enc($t['slug']) . '/', '0.5'];
        foreach (DB::all('SELECT slug FROM brands WHERE active = 1') as $b) $urls[] = ['/brand/' . slug_enc($b['slug']) . '/', '0.5'];
        if (feature('practitioners')) foreach (DB::all('SELECT slug FROM practitioners WHERE active = 1') as $x) $urls[] = ['/apiterapevty/' . slug_enc($x['slug']) . '/', '0.7'];
        foreach (DB::all('SELECT slug FROM places WHERE active = 1') as $x) $urls[] = ['/pasiky/' . slug_enc($x['slug']) . '/', '0.7'];
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '<url><loc>' . htmlspecialchars($abs($u[0])) . '</loc><priority>' . $u[1] . '</priority>';
            if (!empty($u[2])) echo '<lastmod>' . date('Y-m-d', strtotime($u[2])) . '</lastmod>';
            echo "</url>\n";
        }
        echo '</urlset>';
        exit;
    }

    public static function manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => cfg('app_name'), 'short_name' => 'Медоїжка',
            'start_url' => base_url('/admin'), 'scope' => base_url('/'),
            'display' => 'standalone', 'background_color' => '#ffffff', 'theme_color' => '#f7b052',
            'icons' => [
                ['src' => asset('img/brand/logo-medoizhka.webp'), 'sizes' => '1181x1181', 'type' => 'image/webp', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function serviceWorker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . base_url('/'));
        readfile(BOFU_ROOT . '/assets/js/sw.js');
        exit;
    }
}
