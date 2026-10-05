<?php
declare(strict_types=1);
/**
 * Збирає стилі вітрини в один мініфікований файл assets/css/site.min.css.
 *
 *   php bin/build-css.php
 *
 * Навіщо: п'ять окремих файлів стилів блокували перший показ сторінки (на телефоні — понад секунду),
 * а в app.css ще й багато зайвого пробілу. Один файл — один запит і ~35% менше байтів.
 *
 * Порядок той самий, що був у layouts/main.php (від нього залежить, чия правила перекривають чиї).
 * Вихідні файли лишаються як є — ПІСЛЯ їх правки запустіть цей скрипт і закомітьте site.min.css.
 * Адмінпанель збирання не використовує: вона підключає app.css і admin-medoizhka.css напряму.
 */
define('BOFU_ROOT', dirname(__DIR__));
$dir = BOFU_ROOT . '/assets/css/';
$order = ['app.css', 'site.css', 'v3.css', 'shop.css', 'medoizhka.css'];

function minify(string $css): string
{
    // рядки в лапках ховаємо, щоб не зачепити пробіли всередині content:"…"
    $strings = [];
    // Коментарі й рядки — одним проходом: апостроф у коментарі не має «відкривати» рядок,
    // а «/*» усередині рядка не має «відкривати» коментар
    $css = preg_replace_callback('#/\*.*?\*/|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'#s', function ($m) use (&$strings) {
        if ($m[0][0] === '/') return '';
        $strings[] = $m[0]; return "\x01" . (count($strings) - 1) . "\x02";
    }, $css);
    $css = preg_replace('~\s+~', ' ', $css);                        // пробіли й переноси
    $css = preg_replace('#\s*([{};,>])\s*#', '$1', $css);           // навколо розділювачів
    $css = preg_replace('~\s*:\s*(?![^{}]*\{)~', ':', $css);        // у властивостях, не в селекторах
    $css = str_replace(';}', '}', $css);
    return preg_replace_callback('~\x01(\d+)\x02~', fn($m) => $strings[(int)$m[1]], trim($css));
}

$out = ''; $before = 0;
foreach ($order as $f) {
    $src = (string)file_get_contents($dir . $f);
    $before += strlen($src);
    $out .= "/* $f */\n" . minify($src) . "\n";
}
file_put_contents($dir . 'site.min.css', $out);
printf("site.min.css: %d КБ (було %d КБ у %d файлах)\n", round(strlen($out) / 1024), round($before / 1024), count($order));
