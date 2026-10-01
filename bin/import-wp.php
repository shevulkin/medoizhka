<?php
declare(strict_types=1);
/**
 * Перенесення каталогу й сторінок зі старого сайту (WordPress + WooCommerce).
 *
 *   php bin/import-wp.php            — усе
 *   php bin/import-wp.php products   — лише товари (categories | tags | brands | pages | products)
 *
 * Джерело — відкритий REST старого сайту. Слаги зберігаються такими, якими були
 * (кирилиця теж): адреси мають лишитись тими самими, щоб не втратити індексацію.
 * SEO-заголовок і опис беруться з Yoast, тож у видачі сніпети не змінюються.
 * Повторний запуск оновлює записи, а не дублює їх.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

const WP = 'https://medoizhka.com/wp-json';
$only = $argv[1] ?? 'all';

function http(string $url, bool $json = true)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 40,
        CURLOPT_USERAGENT => 'Mozilla/5.0 medoizhka-migration', CURLOPT_SSL_VERIFYPEER => false]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || $body === false) return null;
    return $json ? json_decode($body, true) : $body;
}

function wpAll(string $endpoint, string $extra = ''): array
{
    $all = [];
    for ($page = 1; $page < 20; $page++) {
        $chunk = http(WP . "/$endpoint?per_page=100&page=$page$extra");
        if (!is_array($chunk) || !$chunk || isset($chunk['code'])) break;
        $all = array_merge($all, $chunk);
        if (count($chunk) < 100) break;
    }
    return $all;
}

function txt(?string $html): string
{
    return trim(html_entity_decode(strip_tags((string)$html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** Слаг із WP приходить у відсоткових кодах: у базі тримаємо його читабельним */
function slugIn(string $s): string { return rawurldecode($s); }

function seoOf(array $r): array
{
    $y = $r['yoast_head_json'] ?? [];
    return [txt($y['title'] ?? '') ?: null, txt($y['description'] ?? '') ?: null];
}

function download(string $src): ?string
{
    $dir = BOFU_ROOT . '/assets/uploads/wp';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = preg_replace('~[^a-zA-Z0-9._-]+~', '-', rawurldecode(basename(parse_url($src, PHP_URL_PATH))));
    $dest = "$dir/$name";
    if (!is_file($dest)) {
        $data = http($src, false);
        if ($data === null) return null;
        file_put_contents($dest, $data);
    }
    return "uploads/wp/$name";
}

function upsert(string $table, string $keyCol, $keyVal, array $data): int
{
    $row = DB::row("SELECT id FROM $table WHERE $keyCol = ?", [$keyVal]);
    if ($row) { DB::update($table, $data, 'id = ?', [$row['id']]); return (int)$row['id']; }
    return DB::insert($table, $data + [$keyCol => $keyVal]);
}

$run = fn(string $what) => $only === 'all' || $only === $what;
$catMap = []; $tagMap = []; $brandMap = [];

// ---- категорії (WP id → наш id) ----
$wpCats = wpAll('wp/v2/product_cat');
if ($run('categories') || $run('products')) {
    usort($wpCats, fn($a, $b) => $a['parent'] <=> $b['parent']);
    foreach ($wpCats as $c) {
        if ($c['slug'] === 'uncategorized') continue;
        [$st, $sd] = seoOf($c);
        $parent = $c['parent'] ? ($catMap[$c['parent']] ?? null) : null;
        $id = upsert('categories', 'slug', slugIn($c['slug']), [
            'name' => txt($c['name']), 'parent_id' => $parent, 'type' => 'product', 'active' => 1,
            'description' => txt($c['description'] ?? '') ?: null, 'seo_title' => $st, 'seo_desc' => $sd,
        ]);
        $catMap[$c['id']] = $id;
    }
    echo "Категорій: " . count($catMap) . "\n";
}
if (!$catMap) foreach ($wpCats as $c) { $r = DB::row('SELECT id FROM categories WHERE slug = ?', [slugIn($c['slug'])]); if ($r) $catMap[$c['id']] = (int)$r['id']; }

// ---- теги ----
$wpTags = wpAll('wp/v2/product_tag');
foreach ($wpTags as $t) {
    [$st, $sd] = seoOf($t);
    $tagMap[$t['id']] = upsert('tags', 'slug', slugIn($t['slug']), [
        'name' => txt($t['name']), 'description' => txt($t['description'] ?? '') ?: null, 'seo_title' => $st, 'seo_desc' => $sd]);
}
if ($run('tags')) echo "Тегів: " . count($tagMap) . "\n";

// ---- бренди ----
$wpBrands = wpAll('wp/v2/product_brand');
foreach ($wpBrands as $b) {
    [$st, $sd] = seoOf($b);
    $brandMap[$b['id']] = upsert('brands', 'slug', slugIn($b['slug']), [
        'name' => txt($b['name']), 'description' => txt($b['description'] ?? '') ?: null,
        'own' => in_array($b['slug'], ['medoizhka', 'beekeeper-of-ukraine-and-medoizhka'], true) ? 1 : 0,
        'active' => 1, 'seo_title' => $st, 'seo_desc' => $sd]);
}
if ($run('brands')) echo "Брендів: " . count($brandMap) . "\n";

// ---- сторінки ----
if ($run('pages')) {
    $n = 0;
    foreach (wpAll('wp/v2/pages') as $p) {
        // Сторінка крамниці WordPress — це /shop/: її SEO зберігаємо в налаштуваннях каталогу
        if ($p['slug'] === 'shop') { [$st, $sd] = seoOf($p); Settings::set('shop_seo_title', (string)$st); Settings::set('shop_seo_desc', (string)$sd); continue; }
        if (in_array($p['slug'], ['home', 'shop', 'cart', 'checkout', 'my-account', 'login-customizer'], true)) continue;
        [$st, $sd] = seoOf($p);
        upsert('pages', 'slug', slugIn($p['slug']), [
            'title' => txt($p['title']['rendered'] ?? ''), 'body' => $p['content']['rendered'] ?? '',
            'seo_title' => $st, 'seo_desc' => $sd, 'updated_at' => now()]);
        $n++;
    }
    echo "Сторінок: $n\n";
}

// ---- товари ----
if ($run('products')) {
    $fallbackCat = (int)(DB::val('SELECT id FROM categories ORDER BY id LIMIT 1') ?: 0);
    $n = 0;
    foreach (wpAll('wp/v2/product', '&_embed=wp:featuredmedia') as $p) {
        $slug = slugIn($p['slug']);
        [$st, $sd] = seoOf($p);
        $cats = $p['product_cat'] ?? [];
        $cat = null;
        foreach ($cats as $cid) if (isset($catMap[$cid])) { $cat = $catMap[$cid]; break; }
        $cat ??= $fallbackCat;

        // Ціна: з JSON-LD сторінки товару (Yoast/Woo віддають offers)
        $price = null; $old = null;
        $html = http($p['link'], false);
        if ($html && preg_match_all('~<script type="application/ld\+json"[^>]*>(.*?)</script>~s', $html, $m)) {
            foreach ($m[1] as $blob) {
                if (!preg_match('~"lowPrice"\s*:\s*"?([\d.]+)|"price"\s*:\s*"?([\d.]+)~', $blob, $pm)) continue;
                $price = (float)($pm[1] ?: $pm[2]);
                break;
            }
        }
        $media = $p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? null;
        $img = $media ? download($media) : null;

        $existing = DB::row('SELECT id FROM products WHERE slug = ?', [$slug]);
        $data = [
            'category_id' => $cat, 'name' => txt($p['title']['rendered'] ?? ''),
            'short_desc' => txt($p['excerpt']['rendered'] ?? '') ?: null,
            'description' => $p['content']['rendered'] ?? null,
            'base_price' => $price, 'type' => 'product', 'active' => 1, 'made_to_order' => 1,
            'image' => $img, 'seo_title' => $st, 'seo_desc' => $sd, 'wp_id' => $p['id'],
            'updated_at' => now(),
        ];
        if ($existing) { DB::update('products', $data, 'id = ?', [$existing['id']]); $pid = (int)$existing['id']; }
        else { $pid = DB::insert('products', $data + ['slug' => $slug, 'created_at' => now()]); }

        DB::delete('product_images', 'product_id = ?', [$pid]);
        if ($img) {
            $abs = BOFU_ROOT . '/assets/' . $img; $sz = @getimagesize($abs);
            DB::insert('product_images', ['product_id' => $pid, 'path' => $img, 'width' => $sz[0] ?? 0,
                'height' => $sz[1] ?? 0, 'bytes' => @filesize($abs) ?: 0, 'sort' => 0]);
        }
        // Варіанти (об'єм, вага…): WooCommerce кладе їх у data-product_variations форми
        if ($html && preg_match('~data-product_variations="([^"]*)"~', $html, $vm)) {
            $vars = json_decode(html_entity_decode($vm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true) ?: [];
            $labels = [];   // "attribute_pa_volume" => ["500ml" => "500 мл"]
            if (preg_match_all('~<select[^>]*name="(attribute_[^"]+)"[^>]*>(.*?)</select>~s', $html, $sm, PREG_SET_ORDER)) {
                foreach ($sm as $sel) {
                    preg_match_all('~<option value="([^"]+)"[^>]*>([^<]*)</option>~', $sel[2], $om, PREG_SET_ORDER);
                    foreach ($om as $o) $labels[$sel[1]][$o[1]] = txt($o[2]);
                }
            }
            DB::delete('product_variants', 'product_id = ?', [$pid]);
            $prices = [];
            foreach ($vars as $i => $v) {
                $parts = [];
                foreach ($v['attributes'] ?? [] as $k => $val) $parts[] = $labels[$k][$val] ?? ($val !== '' ? $val : null);
                $name = implode(' / ', array_filter($parts)) ?: ('Варіант ' . ($i + 1));
                $vp = isset($v['display_price']) ? (float)$v['display_price'] : null;
                $prices[] = $vp;
                DB::insert('product_variants', ['product_id' => $pid, 'name' => $name, 'price' => $vp,
                    'sku' => ($v['sku'] ?? '') ?: null, 'sort' => $i, 'active' => 1]);
            }
            if ($prices) DB::update('products', ['base_price' => min(array_filter($prices, fn($x) => $x !== null) ?: [null])], 'id = ?', [$pid]);
        }
        // Залишки: на старому сайті «є в наявності» — ставимо з запасом, далі веде адмінка
        $store = (int)DB::val('SELECT id FROM stores ORDER BY id LIMIT 1');
        if ($store && !DB::row('SELECT id FROM store_stock WHERE product_id = ?', [$pid])) {
            $vids = array_column(DB::all('SELECT id FROM product_variants WHERE product_id = ?', [$pid]), 'id');
            foreach ($vids ?: [null] as $vid) DB::insert('store_stock', ['product_id' => $pid, 'variant_id' => $vid, 'store_id' => $store, 'qty' => 50]);
        }
        DB::delete('product_tags', 'product_id = ?', [$pid]);
        foreach ($p['product_tag'] ?? [] as $tid) if (isset($tagMap[$tid])) DB::insert('product_tags', ['product_id' => $pid, 'tag_id' => $tagMap[$tid]]);
        DB::delete('product_brands', 'product_id = ?', [$pid]);
        foreach ($p['product_brand'] ?? [] as $bid) if (isset($brandMap[$bid])) DB::insert('product_brands', ['product_id' => $pid, 'brand_id' => $brandMap[$bid]]);
        $n++;
        echo ".";
    }
    echo "\nТоварів: $n\n";
}

// ---- фото категорій (квадрат 800px, webp) ----
if ($run('categories') || $run('products')) {
    $dir = BOFU_ROOT . '/assets/uploads/cat';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $n = 0;
    foreach (http(WP . '/wc/store/v1/products/categories?per_page=100') ?: [] as $c) {
        $src = $c['image']['src'] ?? null;
        if (!$src) continue;
        $full = preg_replace('~-\d+x\d+(\.[a-z]+)$~i', '$1', $src);
        $data = http($full, false) ?? http($src, false);
        $im = $data ? @imagecreatefromstring($data) : false;
        if (!$im) continue;
        $w = imagesx($im); $h = imagesy($im); $side = min($w, $h);
        $sq = imagecrop($im, ['x' => intdiv($w - $side, 2), 'y' => intdiv($h - $side, 2), 'width' => $side, 'height' => $side]);
        $rel = 'uploads/cat/' . preg_replace('~[^a-z0-9-]+~', '-', slugIn($c['slug'])) . '.webp';
        imagewebp(imagescale($sq, min(800, $side)), BOFU_ROOT . '/assets/' . $rel, 80);
        DB::update('categories', ['image' => $rel], 'slug = ?', [slugIn($c['slug'])]);
        $n++;
    }
    echo "Фото категорій: $n\n";
}
