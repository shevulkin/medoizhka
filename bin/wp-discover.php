<?php
declare(strict_types=1);
/**
 * Розвідка старої бази WordPress перед переносом даних. ЛИШЕ ЧИТАЄ.
 *
 *   php bin/wp-discover.php [шлях до старого wp-config.php]
 *   (за замовчуванням ~/public_html/medoizhka-v2/wp-config.php)
 *
 * Виводить лише кількості, назви таблиць, ключів і плагінів — жодних імен, пошт,
 * телефонів чи паролів. Вивід можна безпечно скопіювати в чат розробнику.
 */
$cfg = $argv[1] ?? (getenv('HOME') . '/public_html/medoizhka-v2/wp-config.php');
if (!is_file($cfg)) { fwrite(STDERR, "Не знайдено $cfg — вкажіть шлях до старого wp-config.php аргументом\n"); exit(1); }
$src = file_get_contents($cfg);
$def = function (string $k) use ($src): string {
    return preg_match("~define\(\s*['\"]" . $k . "['\"]\s*,\s*['\"](.*?)['\"]\s*\)~", $src, $m) ? stripcslashes($m[1]) : '';
};
$prefix = preg_match('~\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]~', $src, $m) ? $m[1] : 'wp_';
$host = $def('DB_HOST') ?: 'localhost';
$port = 3306;
if (str_contains($host, ':')) [$host, $port] = explode(':', $host, 2);
$pdo = new PDO("mysql:host=$host;port=$port;dbname=" . $def('DB_NAME') . ';charset=utf8mb4', $def('DB_USER'), $def('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$q = fn(string $sql, array $a = []) => ($s = $pdo->prepare($sql)) && $s->execute($a) ? $s->fetchAll(PDO::FETCH_ASSOC) : [];
$v = fn(string $sql, array $a = []) => ($r = $q($sql, $a)) ? array_values($r[0])[0] : null;
$t = fn(string $name) => $prefix . $name;
$exists = fn(string $table) => (bool)$q('SHOW TABLES LIKE ?', [$table]);
$opt = fn(string $name) => $v("SELECT option_value FROM {$t('options')} WHERE option_name = ?", [$name]);

echo "== База: таблиці з префіксом $prefix (рядків)\n";
foreach ($q('SHOW TABLES') as $row) {
    $name = array_values($row)[0];
    if (!str_starts_with($name, $prefix)) continue;
    echo '  ', str_pad(substr($name, strlen($prefix)), 44), (int)$v("SELECT COUNT(*) FROM `$name`"), "\n";
}

echo "\n== Активні плагіни\n";
foreach ((array)@unserialize((string)$opt('active_plugins')) as $p) echo "  $p\n";

echo "\n== Користувачі\n";
echo '  всього: ', (int)$v("SELECT COUNT(*) FROM {$t('users')}"), "\n";
foreach ($q("SELECT meta_value, COUNT(*) n FROM {$t('usermeta')} WHERE meta_key = ? GROUP BY meta_value", [$prefix . 'capabilities']) as $r) {
    echo '  ролі ', implode(',', array_keys((array)@unserialize($r['meta_value']))), ': ', $r['n'], "\n";
}

echo "\n== Ключі usermeta, схожі на курси / прогрес / нотатки (ключ: скільки людей)\n";
foreach ($q("SELECT meta_key, COUNT(DISTINCT user_id) n FROM {$t('usermeta')}
             WHERE meta_key REGEXP 'mq|course|lesson|progress|watch|note|video|learn|bunny|tutor|ld_|llms|access'
             GROUP BY meta_key ORDER BY n DESC LIMIT 60") as $r) echo "  {$r['meta_key']}: {$r['n']}\n";
// приклад формату значення — лише структура, без даних людини
foreach ($q("SELECT meta_key, meta_value FROM {$t('usermeta')} WHERE meta_key REGEXP 'mq' LIMIT 6") as $r) {
    $val = (string)$r['meta_value'];
    $shape = preg_replace(['~"[^"]{0,200}"~', '~\d+~'], ['"…"', '9'], $val);
    echo "  форма {$r['meta_key']}: ", mb_substr($shape, 0, 200), "\n";
}

echo "\n== Окремі таблиці плагіна курсів (якщо є)\n";
foreach ($q('SHOW TABLES') as $row) {
    $name = array_values($row)[0];
    if (!preg_match('~mq|course|lesson|progress|note~i', $name)) continue;
    $cols = array_column($q("SHOW COLUMNS FROM `$name`"), 'Field');
    echo "  $name: ", implode(', ', $cols), "\n";
}

echo "\n== Записи-курси (де стоїть плеєр) і як обмежено доступ\n";
foreach ($q("SELECT ID, post_name, post_status FROM {$t('posts')} WHERE post_type = 'post' AND post_content LIKE '%mq_%' OR post_name IN ('volynec','dukarev','bilko-group-03')") as $r) {
    $metaKeys = array_column($q("SELECT DISTINCT meta_key FROM {$t('postmeta')} WHERE post_id = ? AND meta_key NOT LIKE '\\_edit%'", [$r['ID']]), 'meta_key');
    echo "  #{$r['ID']} {$r['post_name']} ({$r['post_status']}) мета: ", implode(', ', $metaKeys), "\n";
}
foreach ($q("SELECT option_name FROM {$t('options')} WHERE option_name REGEXP 'mq|course' LIMIT 30") as $r) echo "  option: {$r['option_name']}\n";

echo "\n== WooCommerce\n";
$hpos = $exists($t('wc_orders'));
echo '  сховище замовлень: ', $hpos ? 'HPOS (wc_orders)' : 'posts (shop_order)', "\n";
if ($hpos) foreach ($q("SELECT status, COUNT(*) n FROM {$t('wc_orders')} WHERE type = 'shop_order' GROUP BY status") as $r) echo "  {$r['status']}: {$r['n']}\n";
else foreach ($q("SELECT post_status, COUNT(*) n FROM {$t('posts')} WHERE post_type = 'shop_order' GROUP BY post_status") as $r) echo "  {$r['post_status']}: {$r['n']}\n";
echo '  купонів: ', (int)$v("SELECT COUNT(*) FROM {$t('posts')} WHERE post_type = 'shop_coupon'"), "\n";
echo '  відгуків на товари: ', (int)$v("SELECT COUNT(*) FROM {$t('comments')} WHERE comment_type = 'review'"), "\n";
echo "  увімкнені способи оплати:\n";
foreach ($q("SELECT option_name, option_value FROM {$t('options')} WHERE option_name LIKE 'woocommerce\\_%\\_settings'") as $r) {
    $s = @unserialize((string)$r['option_value']);
    if (is_array($s) && ($s['enabled'] ?? '') === 'yes') echo '    ', preg_replace('~^woocommerce_|_settings$~', '', $r['option_name']), "\n";
}

echo "\n== Інше, що може знадобитись\n";
foreach (['wp_mail_smtp' => 'WP Mail SMTP', 'redirection_options' => 'Redirection', 'mailpoet_settings' => 'MailPoet'] as $o => $label) {
    echo "  $label: ", $opt($o) !== null ? 'є налаштування' : 'немає', "\n";
}
if ($exists($t('redirection_items'))) echo '  ручних редиректів (Redirection): ', (int)$v("SELECT COUNT(*) FROM {$t('redirection_items')}"), "\n";
echo "\nГотово. Нічого не змінено.\n";
