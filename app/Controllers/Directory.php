<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Auth, Csrf, RateLimit, JsonLd, Hub as Dir;

/** Сторінки каталогів: апітерапевти та пасіки/апібудиночки, плюс форми заявок. */
class Directory
{
    public static function practitioners(): never
    {
        $f = ['region' => trim((string)($_GET['region'] ?? '')), 'online' => !empty($_GET['online']), 'q' => trim((string)($_GET['q'] ?? ''))];
        View::show('directory/practitioners', [
            'items' => Dir::practitioners($f), 'f' => $f, 'regions' => Dir::regions('practitioners'),
            'page_title' => seo_title('Апітерапевти України — запис на консультацію'),
            'meta_description' => 'Каталог апітерапевтів: спеціалізація, місто, формат (очно чи онлайн), орієнтовна вартість. Залиште заявку — фахівець зв’яжеться з вами.',
            'jsonld' => [JsonLd::breadcrumbs([['Головна', '/'], ['Апітерапевти', null]])],
        ]);
    }

    public static function practitioner(string $slug): never
    {
        $p = DB::row('SELECT * FROM practitioners WHERE slug = ? AND active = 1', [$slug]);
        if (!$p) { http_response_code(404); View::show('errors/404'); }
        View::show('directory/practitioner', [
            'p' => $p, 'sent' => !empty($_GET['sent']),
            'page_title' => seo_title($p['name'] . ($p['title'] ? ', ' . $p['title'] : '')),
            'meta_description' => seo_desc($p['bio'], $p['title']),
            'canonical' => abs_url('/apiterapevty/' . slug_enc($p['slug']) . '/'),
            'jsonld' => [
                ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $p['name'], 'jobTitle' => $p['title'],
                 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $p['city'], 'addressCountry' => 'UA']],
                JsonLd::breadcrumbs([['Головна', '/'], ['Апітерапевти', '/apiterapevty/'], [$p['name'], null]]),
            ],
        ]);
    }

    public static function places(): never
    {
        $f = ['kind' => (string)($_GET['type'] ?? ''), 'region' => trim((string)($_GET['region'] ?? ''))];
        View::show('directory/places', [
            'items' => Dir::places($f), 'f' => $f, 'regions' => Dir::regions('places'),
            // порожній розділ і відфільтровані варіанти — не для індексу (дублі й «місць немає»)
            'noindex' => !Dir::hasPlaces() || $f['kind'] !== '' || $f['region'] !== '',
            'canonical' => abs_url('/pasiky/'),
            'page_title' => seo_title('Відвідування пасік, апібудиночки та майстер-класи'),
            'meta_description' => 'Платні відвідування пасік, відпочинок в апібудиночках, дихання бджолиним повітрям і майстер-класи: опис, вартість, як дістатися й запис онлайн.',
            'jsonld' => [JsonLd::breadcrumbs([['Головна', '/'], ['Пасіки й апібудиночки', null]])],
        ]);
    }

    public static function place(string $slug): never
    {
        $p = DB::row('SELECT * FROM places WHERE slug = ? AND active = 1', [$slug]);
        if (!$p) { http_response_code(404); View::show('errors/404'); }
        View::show('directory/place', [
            'p' => $p, 'sent' => !empty($_GET['sent']),
            'page_title' => seo_title($p['name'] . ' — ' . mb_strtolower(Dir::kindLabel($p['kind']))),
            'meta_description' => seo_desc($p['summary'], $p['description']),
            'canonical' => abs_url('/pasiky/' . slug_enc($p['slug']) . '/'),
            'jsonld' => [
                ['@context' => 'https://schema.org', '@type' => $p['kind'] === 'apihouse' ? 'LodgingBusiness' : 'TouristAttraction',
                 'name' => $p['name'], 'description' => $p['summary'],
                 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $p['city'], 'addressCountry' => 'UA']],
                JsonLd::breadcrumbs([['Головна', '/'], ['Пасіки й апібудиночки', '/pasiky/'], [$p['name'], null]]),
            ],
        ]);
    }

    /** POST заявки: /apiterapevty/{слаг}/zapys/ і /pasiky/{слаг}/zapys/ */
    public static function book(string $kind, string $slug): never
    {
        Csrf::verify();
        RateLimit::guard('booking', 10, 3600);
        $table = $kind === 'place' ? 'places' : 'practitioners';
        $base = $kind === 'place' ? '/pasiky/' : '/apiterapevty/';
        $row = DB::row("SELECT * FROM $table WHERE slug = ? AND active = 1", [$slug]);
        if (!$row) { http_response_code(404); View::show('errors/404'); }
        $back = $base . slug_enc($slug) . '/';
        // Поле-пастка для ботів: людина його не бачить і не заповнює
        if (trim((string)($_POST['website'] ?? '')) !== '') redirect($back . '?sent=1');
        $id = Dir::saveBooking($kind, $row, $_POST);
        if (!$id) { flash('error', 'Вкажіть імʼя та телефон, щоб ми могли відповісти.'); redirect($back . '#zapys'); }
        redirect($back . '?sent=1#zapys');
    }
}
