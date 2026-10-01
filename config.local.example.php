<?php
/**
 * Зразок налаштувань сервера. Скопіюйте як config.local.php (у git він не потрапляє)
 * і впишіть доступ до бази MySQL, створеної в cPanel → MySQL Databases.
 */
return [
    'env'   => 'production',
    'debug' => false,
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'cpaneluser_medoizhka',
        'username' => 'cpaneluser_medoizhka',
        'password' => 'ВАШ_ПАРОЛЬ_БАЗИ',
    ],
    // Bunny Stream: ключ підписаних посилань (можна задати і в адмінці → «Уроки курсів і Bunny»)
    // 'bunny' => ['library' => '573243', 'token_key' => ''],
];
