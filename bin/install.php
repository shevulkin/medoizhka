<?php
declare(strict_types=1);
/**
 * Перше встановлення Медоїжки на сервері — одна команда:
 *
 *   php bin/install.php
 *
 * Перед цим у корені сайту має лежати config.local.php з доступом до бази
 * (зразок — config.local.example.php). Скрипт по черзі:
 *   1. створює таблиці (migrate) і початкові налаштування (seed);
 *   2. переносить каталог зі старого сайту: товари з фото, категорії з фото, теги,
 *      бренди, сторінки, SEO-заголовки (import-wp);
 *   3. переносить картинки зі сторінок у власне сховище (localize-images);
 *   4. створює відеокурси й уроки (import-courses);
 *   5. заповнює контакти й соцмережі (seed-content);
 *   6. зводить усі фото в одну теку assets/uploads/, стискає у webp (tidy-media).
 * Повторний запуск безпечний: кожен крок оновлює наявне, а не дублює.
 */
$php = PHP_BINARY ?: 'php';
// у cPanel PHP_BINARY іноді вказує на php-cgi/php-fpm — тоді беремо php з PATH
if (preg_match('~(cgi|fpm)~i', basename($php))) $php = 'php';
$steps = [
    ['cli.php', 'migrate'],
    ['cli.php', 'seed'],
    ['import-wp.php', ''],
    ['localize-images.php', ''],
    ['import-courses.php', ''],
    ['seed-content.php', ''],
    ['tidy-media.php', ''],
];
foreach ($steps as [$file, $arg]) {
    echo "\n=== $file $arg ===\n";
    // proc_open з масивом — без оболонки: на Windows cmd.exe ламав лапки, і дочірній
    // PHP, не отримавши шляху до скрипта, мовчки чекав коду зі стандартного вводу
    $cmd = array_values(array_filter([$php, __DIR__ . '/' . $file, $arg], fn($x) => $x !== ''));
    $proc = proc_open($cmd, [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
    $code = is_resource($proc) ? proc_close($proc) : 1;
    if ($code !== 0) { echo "\nКрок $file завершився з помилкою ($code). Виправте й запустіть install.php ще раз.\n"; exit($code); }
}
echo "\nГотово. Сайт закритий від пошуковиків (seo_noindex) — відкрийте його в адмінці, коли переключите домен.\n";
