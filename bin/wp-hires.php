<?php
declare(strict_types=1);
/**
 * Підвищує якість фото товарів: бере ОРИГІНАЛИ зі старої теки WordPress (лише читає) і перезаписує
 * ними наявні файли в assets/uploads/ — до 2000 px, якість 90 (раніше були зменшені копії зі сторінок
 * сайту: 800–1600 px, якість ~80). Адреси фото в базі не змінюються. Також перероблює -thumb і -md.
 *
 *   php bin/wp-hires.php                          — показати, що покращиться (нічого не записує)
 *   php bin/wp-hires.php --apply                  — записати
 *   php bin/wp-hires.php --apply [шлях до старого wp-config.php]
 *
 * Фото зіставляються за назвою файлу (так їх називали tidy-media і wp-images). Замінюється лише тоді,
 * коли оригінал помітно більший за наявне (на 15% і більше по довшій стороні). Повторний запуск безпечний.
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
$fileOf = function (int $att) use ($meta, $q, $prefix, $uploads): ?string {
    $rel = $meta($att, '_wp_attached_file');
    if ($rel === '') {
        $g = $q("SELECT guid FROM {$prefix}posts WHERE ID = ?", [$att]);
        if ($g && preg_match('~/wp-content/uploads/(.+)$~', (string)$g[0]['guid'], $m)) $rel = urldecode($m[1]);
    }
    $abs = $rel !== '' ? $uploads . '/' . ltrim($rel, '/') : '';
    return $abs !== '' && is_file($abs) ? $abs : null;
};
function baseKey(string $base): string
{
    $base = preg_replace(['~-\d+x\d+$~', '~-scaled$~', '~(?:-photoroom)+$~i'], '', $base);
    return slugify($base) ?: 'foto';
}
/** Ключ наявного файлу: назва без розширення й без числового суфікса -2, -3 */
function haveKey(string $path): string { return preg_replace('~-\d+$~', '', pathinfo($path, PATHINFO_FILENAME)); }

/** Перезаписує webp оригіналом: до 2000 px, якість 90; -thumb і -md — з нього */
function rewrite(string $src, string $targetAbs): ?array
{
    $im = @imagecreatefromstring((string)@file_get_contents($src));
    if (!$im) return null;
    $w = imagesx($im); $h = imagesy($im);
    $sc = min(1, Images::MAX_SIDE / max($w, $h)); $nw = (int)round($w * $sc); $nh = (int)round($h * $sc);
    $dst = imagecreatetruecolor($nw, $nh); imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagewebp($dst, $targetAbs, Images::QUALITY);
    $ts = min(1, Images::THUMB_SIDE / max($nw, $nh)); $tw = (int)round($nw * $ts); $th = (int)round($nh * $ts);
    $t = imagecreatetruecolor($tw, $th); imagealphablending($t, false); imagesavealpha($t, true);
    imagecopyresampled($t, $dst, 0, 0, 0, 0, $tw, $th, $nw, $nh);
    imagewebp($t, preg_replace('~\.webp$~', '-thumb.webp', $targetAbs), Images::THUMB_QUALITY);
    Images::makeMid($targetAbs);
    return [$nw, $nh, (int)filesize($targetAbs)];
}

$upgraded = 0; $checked = 0;
foreach (DB::all('SELECT * FROM products WHERE wp_id IS NOT NULL ORDER BY id') as $p) {
    $pid = (int)$p['id']; $wid = (int)$p['wp_id'];
    $ids = [];
    if (($t = (int)$meta($wid, '_thumbnail_id')) > 0) $ids[] = $t;
    foreach (explode(',', $meta($wid, '_product_image_gallery')) as $g) if ((int)$g > 0 && !in_array((int)$g, $ids, true)) $ids[] = (int)$g;
    foreach ($q("SELECT ID FROM {$prefix}posts WHERE post_parent = ? AND post_type = 'product_variation'", [$wid]) as $v) {
        if (($t = (int)$meta((int)$v['ID'], '_thumbnail_id')) > 0 && !in_array($t, $ids, true)) $ids[] = $t;
    }
    // наявні файли товару за ключем назви
    $have = [];
    foreach (DB::all('SELECT id, path FROM product_images WHERE product_id = ?', [$pid]) as $r) $have[haveKey($r['path'])][] = ['row' => (int)$r['id'], 'path' => $r['path']];
    if ($p['image']) $have[haveKey($p['image'])] ??= [['row' => 0, 'path' => $p['image']]];
    foreach (array_filter(array_map($fileOf, $ids)) as $orig) {
        $key = baseKey(pathinfo($orig, PATHINFO_FILENAME));
        if (empty($have[$key])) continue;
        [$ow, $oh] = @getimagesize($orig) ?: [0, 0];
        if ($ow <= 0) continue;
        $k = min(1, Images::MAX_SIDE / max($ow, $oh));
        foreach ($have[$key] as $h) {
            $abs = BOFU_ROOT . '/assets/' . $h['path'];
            if (!is_file($abs) || !str_ends_with($abs, '.webp')) continue;
            $checked++;
            [$cw, $ch] = @getimagesize($abs) ?: [0, 0];
            if (max($ow, $oh) * $k < max($cw, $ch) * 1.15) continue;
            $upgraded++;
            echo '  ', $p['name'], ': ', $cw, '×', $ch, ' → ', (int)round($ow * $k), '×', (int)round($oh * $k), '  (', basename($h['path']), ")\n";
            if (!$apply) continue;
            $res = rewrite($orig, $abs);
            if ($res && $h['row']) DB::update('product_images', ['width' => $res[0], 'height' => $res[1], 'bytes' => $res[2]], 'id = ?', [$h['row']]);
        }
    }
}
echo "\nПеревірено фото: $checked, буде покращено: $upgraded\n";
echo $apply ? "Записано. Якщо сайт показує старі фото — оновіть сторінку з Ctrl+F5.\n" : "\nЦе перегляд — нічого не записано. Щоб записати, додайте --apply\n";
