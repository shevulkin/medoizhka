<?php
declare(strict_types=1);
/**
 * Наводить лад у фото: усе — в одну теку assets/uploads/, формат webp, не ширше 1600px
 * (+ превʼю 480px з суфіксом -thumb, як у фото, завантажених в адмінці).
 *
 *   php bin/tidy-media.php
 *
 * Що робить:
 *  1. Переносить фото зі службових тек імпорту (uploads/wp, uploads/cat) у uploads/
 *     під зрозумілими назвами (фото категорій — з префіксом kategoriia-), стискає.
 *  2. Оновлює всі посилання в базі: товари, галереї, категорії, тексти сторінок і описів.
 *  3. Лагодить посилання на старий WordPress (…/wp-content/uploads/…): логотип — на
 *     фірмовий файл сайту, решту — на перенесену копію, а якщо копії немає — прибирає <img>.
 * Повторний запуск безпечний: те, що вже на місці, не чіпає.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$assets = BOFU_ROOT . '/assets/';
$map = [];   // 'uploads/wp/x.jpg' => 'uploads/x.webp'

function cleanName(string $base): string
{
    $base = preg_replace('~-\d+x\d+$~', '', $base);              // мініатюри WP
    $base = preg_replace('~-scaled$~', '', $base);
    $base = preg_replace('~(?:-photoroom)+$~i', '', $base);       // хвости редакторів
    $s = slugify($base);
    return $s !== '' ? $s : 'foto';
}

function convert(string $src, string $destNoExt): ?array
{
    $data = @file_get_contents($src);
    $im = $data ? @imagecreatefromstring($data) : false;
    if (!$im) return null;
    $w = imagesx($im); $h = imagesy($im);
    $sc = min(1, Images::MAX_SIDE / max($w, $h));
    $nw = (int)round($w * $sc); $nh = (int)round($h * $sc);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagewebp($dst, "$destNoExt.webp", 82);
    $ts = min(1, Images::THUMB_SIDE / max($nw, $nh));
    $tw = (int)round($nw * $ts); $th = (int)round($nh * $ts);
    $t = imagecreatetruecolor($tw, $th);
    imagealphablending($t, false); imagesavealpha($t, true);
    imagecopyresampled($t, $dst, 0, 0, 0, 0, $tw, $th, $nw, $nh);
    imagewebp($t, "$destNoExt-thumb.webp", 80);
    return [$nw, $nh, (int)filesize("$destNoExt.webp")];
}

// 1. Перенос і стискання
$saved = 0; $before = 0; $after = 0;
foreach (['wp' => '', 'cat' => 'kategoriia-'] as $dir => $prefix) {
    foreach (glob($assets . "uploads/$dir/*") ?: [] as $file) {
        if (!is_file($file)) continue;
        $old = "uploads/$dir/" . basename($file);
        $name = $prefix . cleanName(pathinfo($file, PATHINFO_FILENAME));
        $target = $name; $i = 2;
        while (is_file($assets . "uploads/$target.webp")) $target = $name . "-" . $i++;
        $size = filesize($file);
        $res = convert($file, $assets . "uploads/$target");
        if (!$res) { echo "  пропущено (не картинка): $old\n"; continue; }
        $map[$old] = "uploads/$target.webp";
        $before += $size; $after += $res[2]; $saved++;
        @unlink($file);
    }
    @rmdir($assets . "uploads/$dir");
}
printf("Перенесено фото: %d, було %.1f МБ → стало %.1f МБ\n", $saved, $before / 1048576, $after / 1048576);

// 2. Посилання в базі
$n = 0;
foreach ($map as $old => $new) {
    $n += DB::update('products', ['image' => $new], 'image = ?', [$old]);
    $n += DB::update('categories', ['image' => $new], 'image = ?', [$old]);
    $n += DB::update('content_blocks', ['image' => $new], 'image = ?', [$old]);
    $abs = $assets . $new; $sz = @getimagesize($abs);
    $n += DB::update('product_images', ['path' => $new, 'width' => $sz[0] ?? 0, 'height' => $sz[1] ?? 0, 'bytes' => (int)@filesize($abs)], 'path = ?', [$old]);
}

// 3. Тексти сторінок і описів: нові шляхи + лагодження посилань на старий WordPress
$byBase = [];
foreach ($map as $old => $new) $byBase[cleanName(pathinfo($old, PATHINFO_FILENAME))] = $new;
$fixHtml = function (string $html) use ($map, $byBase): string {
    foreach ($map as $old => $new) $html = str_replace('{assets}/' . $old, '{assets}/' . $new, $html);
    // <img> на старий /wp-content/uploads/
    $html = preg_replace_callback('~<img\b[^>]*\bsrc="(https?://[^"]*/wp-content/uploads/[^"]+)"[^>]*>~i', function ($m) use ($byBase) {
        $base = cleanName(pathinfo(parse_url($m[1], PHP_URL_PATH), PATHINFO_FILENAME));
        if (str_contains($base, 'logo-medoizhka')) return str_replace($m[1], '{assets}/img/brand/logo-medoizhka.webp', $m[0]);
        if (isset($byBase[$base])) return str_replace($m[1], '{assets}/' . $byBase[$base], $m[0]);
        return '';   // копії немає — зламаної картинки на сторінці не лишаємо
    }, $html);
    // посилання-обгортки на файли картинок: фото не має відкриватися окремим файлом
    $html = preg_replace('~<a\b[^>]*href="[^"]+\.(?:jpe?g|png|webp|gif)"[^>]*>\s*(<img\b[^>]*>)\s*</a>~i', '$1', $html);
    return $html;
};
foreach (DB::all('SELECT id, body FROM pages') as $p) {
    $b = $fixHtml((string)$p['body']);
    if ($b !== $p['body']) { DB::update('pages', ['body' => $b], 'id = ?', [$p['id']]); $n++; }
}
foreach (DB::all("SELECT id, description FROM products WHERE description LIKE '%<img%' OR description LIKE '%uploads/%'") as $p) {
    $b = $fixHtml((string)$p['description']);
    if ($b !== $p['description']) { DB::update('products', ['description' => $b], 'id = ?', [$p['id']]); $n++; }
}
echo "Оновлено посилань у базі: $n\n";
$left = (int)DB::val("SELECT COUNT(*) FROM pages WHERE body LIKE '%wp-content%'") + (int)DB::val("SELECT COUNT(*) FROM products WHERE description LIKE '%wp-content%'");
echo $left ? "Увага: ще лишились згадки wp-content: $left\n" : "Посилань на старий WordPress не лишилось.\n";
