<?php
declare(strict_types=1);
/**
 * Підключення до СТАРОЇ бази WordPress — спільне для wp-discover.php і wp-stock.php. Лише читання.
 * Сам по собі нічого не робить: його підключають інші скрипти.
 *
 * Де шукаємо налаштування старого сайту, по черзі:
 *  1. шлях до wp-config.php, переданий аргументом;
 *  2. змінні оточення WP_DB_NAME, WP_DB_USER, WP_DB_PASSWORD (+ WP_DB_HOST, WP_TABLE_PREFIX) —
 *     на випадок, коли теку старого сайту вже прибрали, а база лишилась;
 *  3. типові місця: ~/public_html/wp-config.php, ~/public_html/<тека>/wp-config.php, ~/<тека>/wp-config.php
 *     (тека після перейменування може називатись як завгодно: medoizhka-v2, medoizhka-wp-old…).
 *     З кількох знайдених беремо той, у чиїй базі справді є товари WooCommerce.
 *
 * Повертає [PDO, префікс таблиць, звідки взято]. Пароль ніде не друкується.
 */

/** Значення define('KEY', '…') з тексту wp-config.php */
function wpOldDefine(string $src, string $key): string
{
    return preg_match("~define\(\s*['\"]" . $key . "['\"]\s*,\s*['\"](.*?)['\"]\s*\)~", $src, $m) ? stripcslashes($m[1]) : '';
}

/** Параметри підключення з wp-config.php */
function wpOldFromConfig(string $file): array
{
    $src = (string)file_get_contents($file);
    return [
        'name' => wpOldDefine($src, 'DB_NAME'), 'user' => wpOldDefine($src, 'DB_USER'),
        'pass' => wpOldDefine($src, 'DB_PASSWORD'), 'host' => wpOldDefine($src, 'DB_HOST') ?: 'localhost',
        'prefix' => preg_match('~\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]~', $src, $m) ? $m[1] : 'wp_',
        'from' => $file,
    ];
}

function wpOldPdo(array $c): PDO
{
    $host = $c['host']; $port = 3306;
    if (str_contains($host, ':')) [$host, $port] = explode(':', $host, 2);
    return new PDO("mysql:host=$host;port=$port;dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/** Скільки товарів WooCommerce у цій базі; null — не вдалося підключитись */
function wpOldProducts(array $c): ?int
{
    try {
        return (int)wpOldPdo($c)->query("SELECT COUNT(*) FROM `{$c['prefix']}posts` WHERE post_type = 'product'")->fetchColumn();
    } catch (Throwable) {
        return null;
    }
}

function wpOldConnect(?string $arg): array
{
    $fail = function (string $msg): never { fwrite(STDERR, $msg . "\n"); exit(1); };

    if ($arg !== null && $arg !== '') {
        if (!is_file($arg)) $fail("Не знайдено $arg");
        $c = wpOldFromConfig($arg);
    } elseif (getenv('WP_DB_NAME')) {
        $c = ['name' => (string)getenv('WP_DB_NAME'), 'user' => (string)getenv('WP_DB_USER'), 'pass' => (string)getenv('WP_DB_PASSWORD'),
              'host' => (string)(getenv('WP_DB_HOST') ?: 'localhost'), 'prefix' => (string)(getenv('WP_TABLE_PREFIX') ?: 'wp_'),
              'from' => 'змінних оточення WP_DB_*'];
    } else {
        $home = rtrim((string)(getenv('HOME') ?: ''), '/');
        $files = [];
        foreach (["$home/public_html/wp-config.php", "$home/public_html/*/wp-config.php", "$home/*/wp-config.php"] as $pattern) {
            foreach (glob($pattern) ?: [] as $f) if (is_file($f)) $files[realpath($f) ?: $f] = true;
        }
        $found = [];
        foreach (array_keys($files) as $f) {
            $c = wpOldFromConfig($f);
            $n = wpOldProducts($c);
            if ($n) $found[] = $c + ['n' => $n];
        }
        if (!$found) {
            $fail("Не знайшов старого WordPress з товарами в ~/public_html/*/ і ~/*/.\n"
                . (array_keys($files) ? "Перевірені wp-config.php:\n  " . implode("\n  ", array_keys($files)) . "\n" : '')
                . "Знайти вручну:   find ~ -maxdepth 4 -name wp-config.php\n"
                . "і передати шлях: php bin/" . basename($_SERVER['argv'][0] ?? 'wp-stock.php') . " <шлях до wp-config.php>");
        }
        if (count($found) > 1) {
            $fail("Знайшов кілька WordPress-сайтів з товарами — вкажіть потрібний аргументом:\n  "
                . implode("\n  ", array_map(fn($c) => "{$c['from']} (товарів: {$c['n']}, база {$c['name']})", $found)));
        }
        $c = $found[0];
    }

    try {
        $pdo = wpOldPdo($c);
    } catch (Throwable $e) {
        $fail("Не вдалося підключитись до старої бази «{$c['name']}» (з {$c['from']}): " . $e->getMessage());
    }
    fwrite(STDERR, "Стара база: {$c['name']}, префікс {$c['prefix']} (з {$c['from']})\n\n");
    return [$pdo, $c['prefix'], $c['from']];
}
