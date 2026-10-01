<?php
// Лише для локального `php -S`: віддає файли як є, решту — у index.php (на Apache це робить .htaccess)
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p !== '/' && is_file(__DIR__ . $p) && !str_ends_with($p, '.php')) return false;
require __DIR__ . '/index.php';
