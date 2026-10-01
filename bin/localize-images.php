<?php
declare(strict_types=1);
/**
 * Картинки в текстах сторінок і описах товарів посилаються на старий WordPress
 * (/wp-content/uploads/…). Після перемикання домену їх там не буде, тож завантажуємо
 * локально (найбільшу версію, без суфікса -1024x769) і переписуємо посилання.
 *   php bin/localize-images.php
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

function fetchTo(string $url): ?string
{
    $dir = BOFU_ROOT . '/assets/uploads/wp';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $full = preg_replace('~-\d+x\d+(\.[a-z]+)$~i', '$1', $url);   // оригінал замість мініатюри
    foreach (array_unique([$full, $url]) as $try) {
        $name = preg_replace('~[^a-zA-Z0-9._-]+~', '-', rawurldecode(basename(parse_url($try, PHP_URL_PATH))));
        $dest = "$dir/$name";
        if (is_file($dest)) return "uploads/wp/$name";
        $ch = curl_init($try);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => false]);
        $data = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code === 200 && $data) { file_put_contents($dest, $data); return "uploads/wp/$name"; }
    }
    return null;
}
function rewrite(string $html, int &$n): string
{
    return preg_replace_callback('~https?://(?:cdn\.)?medoizhka\.com/wp-content/uploads/[^"\'\s)]+\.(?:jpe?g|png|webp|gif)~i', function ($m) use (&$n) {
        $local = fetchTo($m[0]);
        if (!$local) return $m[0];
        $n++;
        return '{assets}/' . substr($local, 0);
    }, $html);
}
$n = 0;
foreach (DB::all('SELECT id, body FROM pages') as $p) {
    $b = rewrite((string)$p['body'], $n);
    // srcset із мініатюрами старого сайту більше не потрібен
    $b = preg_replace('~\s(srcset|sizes)="[^"]*"~', '', $b);
    DB::update('pages', ['body' => $b], 'id = ?', [$p['id']]);
}
foreach (DB::all('SELECT id, description FROM products WHERE description LIKE ?', ['%wp-content%']) as $p) {
    DB::update('products', ['description' => preg_replace('~\s(srcset|sizes)="[^"]*"~', '', rewrite((string)$p['description'], $n))], 'id = ?', [$p['id']]);
}
echo "Переписано посилань: $n\n";
