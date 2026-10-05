<?php
declare(strict_types=1);
/**
 * Створює середній розмір фото (-md, до 800 px) для всіх файлів у assets/uploads/, де його ще немає.
 * Картки каталогу на екранах високої щільності беруть його замість розмитої 480-px мініатюри.
 *
 *   php bin/make-mid.php
 *
 * Повторний запуск безпечний: наявні -md не чіпає.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$made = 0;
foreach (glob(BOFU_ROOT . '/assets/uploads/*.{webp,jpg,png}', GLOB_BRACE) as $f) {
    if (preg_match('/-(thumb|md)\.(webp|jpg|png)$/', $f)) continue;
    $mid = preg_replace('/\.(webp|jpg|png)$/', '-md.$1', $f);
    if (is_file($mid)) continue;
    if (Images::makeMid($f)) $made++;
}
echo "Створено середніх розмірів: $made\n";
