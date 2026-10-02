<?php
declare(strict_types=1);
/**
 * Фото товарів і курсів, у яких їх немає, — зі старої установки WordPress. Стару базу й
 * теку ЛИШЕ ЧИТАЄ; у нову кладе webp у assets/uploads/ (як tidy-media: 1600px + -thumb 480px).
 *
 *   php bin/wp-images.php                         — показати, що знайдеться (нічого не записує)
 *   php bin/wp-images.php --apply                 — записати
 *   php bin/wp-images.php --apply [шлях до старого wp-config.php]
 *
 * Навіщо: перший імпорт брав фото зі сторінок сайту, а в частини товарів і в курсів
 * (це записи, а не товари WooCommerce) їх там не було видно. У старій базі вони є:
 * головне фото (_thumbnail_id) і галерея (_product_image_gallery), а самі файли —
 * у wp-content/uploads старої теки.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';
require __DIR__ . '/wp-old.php';

$apply = in_array('--apply', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), fn($a) => $a !== '--apply'));
[$wp, $prefix, $from] = wpOldConnect($args[0] ?? null);
$uploads = is_file($from) ? dirname($from) . '/wp-content/uploads' : (getenv('WP_UPLOADS') ?: '');
if (!is_dir($uploads)) { fwrite(STDERR, "Не знайдено теку з фото старого сайту: $uploads (можна вказати WP_UPLOADS=…)\n"); exit(1); }

$q = function (string $sql, array $a = []) use ($wp): array { $s = $wp->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); };
$meta = function (int $id, string $key) use ($q, $prefix): string {
    $r = $q("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1", [$id, $key]);
    return (string)($r[0]['meta_value'] ?? '');
};
/** Шлях до файлу вкладення в старій теці */
$fileOf = function (int $att) use ($meta, $q, $prefix, $uploads): ?string {
    $rel = $meta($att, '_wp_attached_file');
    if ($rel === '') {
        $g = $q("SELECT guid FROM {$prefix}posts WHERE ID = ?", [$att]);
        if ($g && preg_match('~/wp-content/uploads/(.+)$~', (string)$g[0]['guid'], $m)) $rel = urldecode($m[1]);
    }
    $abs = $rel !== '' ? $uploads . '/' . ltrim($rel, '/') : '';
    return $abs !== '' && is_file($abs) ? $abs : null;
};
function cleanBase(string $base): string
{
    $base = preg_replace(['~-\d+x\d+$~', '~-scaled$~', '~(?:-photoroom)+$~i'], '', $base);
    return slugify($base) ?: 'foto';
}
/** webp 1600px + -thumb 480px; повертає [шлях, ширина, висота, байти] */
function toWebp(string $src, string $name): ?array
{
    $im = @imagecreatefromstring((string)@file_get_contents($src));
    if (!$im) return null;
    $dir = BOFU_ROOT . '/assets/uploads';
    $target = $name; $i = 2;
    while (is_file("$dir/$target.webp")) $target = $name . '-' . $i++;
    $w = imagesx($im); $h = imagesy($im);
    $sc = min(1, Images::MAX_SIDE / max($w, $h)); $nw = (int)round($w * $sc); $nh = (int)round($h * $sc);
    $dst = imagecreatetruecolor($nw, $nh); imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagewebp($dst, "$dir/$target.webp", 82);
    $ts = min(1, Images::THUMB_SIDE / max($nw, $nh)); $tw = (int)round($nw * $ts); $th = (int)round($nh * $ts);
    $t = imagecreatetruecolor($tw, $th); imagealphablending($t, false); imagesavealpha($t, true);
    imagecopyresampled($t, $dst, 0, 0, 0, 0, $tw, $th, $nw, $nh);
    imagewebp($t, "$dir/$target-thumb.webp", 80);
    return ["uploads/$target.webp", $nw, $nh, (int)filesize("$dir/$target.webp")];
}

$found = 0; $none = [];
$rows = DB::all("SELECT p.* FROM products p WHERE p.wp_id IS NOT NULL AND (p.image IS NULL OR p.image = '')
                 AND NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id) ORDER BY p.id");
foreach ($rows as $p) {
    $wid = (int)$p['wp_id'];
    $ids = [];
    if (($t = (int)$meta($wid, '_thumbnail_id')) > 0) $ids[] = $t;
    foreach (explode(',', $meta($wid, '_product_image_gallery')) as $g) if ((int)$g > 0 && !in_array((int)$g, $ids, true)) $ids[] = (int)$g;
    $files = array_values(array_filter(array_map($fileOf, $ids)));
    if (!$files) { $none[] = $p['name']; continue; }
    $found++;
    echo '  ', $p['name'], ': ', count($files), ' фото', ($apply ? '' : ' (' . basename($files[0]) . ($files[1] ?? false ? ', …' : '') . ')'), "\n";
    if (!$apply) continue;
    $sort = 0; $main = null;
    foreach ($files as $f) {
        $res = toWebp($f, ($p['type'] === 'course' ? 'kurs-' : '') . cleanBase(pathinfo($f, PATHINFO_FILENAME)));
        if (!$res) continue;
        $main ??= $res[0];
        DB::insert('product_images', ['product_id' => (int)$p['id'], 'path' => $res[0], 'width' => $res[1],
            'height' => $res[2], 'bytes' => $res[3], 'sort' => $sort++]);
    }
    if ($main) DB::update('products', ['image' => $main, 'updated_at' => now()], 'id = ?', [(int)$p['id']]);
}
echo "\nЗнайдено фото для: $found з " . count($rows) . " без фото\n";
if ($none) echo 'У старій базі фото теж немає (лишиться нейтральна заглушка — додайте фото в адмінпанелі): ' . implode(', ', $none) . "\n";
echo $apply ? "Записано.\n" : "\nЦе перегляд — нічого не записано. Щоб записати, додайте --apply\n";
