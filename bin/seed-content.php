<?php
declare(strict_types=1);
/**
 * Контакти й соцмережі зі старого сайту (сторінка «Контакти»).
 * Порожні поля не перезаписуються: якщо адмін уже змінив значення — воно лишається.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$blocks = [
    'contact_phone' => '+38 (063) 819-55-77',
    'contact_email' => 'contact@medoizhka.com',
    'contact_hours' => 'Щодня 8:00–20:00',
    'contact_address' => 'м. Київ, вул. Сержа Лифаря, 4',
    'social_facebook' => 'https://www.facebook.com/medoizhka',
    'social_instagram' => 'https://www.instagram.com/medoizhka',
    'social_tiktok' => 'https://www.tiktok.com/@medoizhka',
];
foreach ($blocks as $key => $val) {
    $row = DB::row('SELECT `key`, title FROM content_blocks WHERE `key` = ?', [$key]);
    if ($row && trim((string)$row['title']) !== '') continue;
    if ($row) DB::update('content_blocks', ['title' => $val], '`key` = ?', [$key]);
    else DB::insert('content_blocks', ['key' => $key, 'title' => $val, 'body' => null, 'image' => null]);
}
// Google зі старого сайту (Site Kit): публічні ідентифікатори, тож статистика й вхід
// через Google продовжуються без розриву. Заповнені вручну значення не перезаписуємо.
foreach ([
    'google_client_id' => '181991088530-np4ncjjo2i0ff8o4rq2nhksuq586b3tl.apps.googleusercontent.com',
    'google_tag_id' => 'GT-M63L9WSM',
    'gtm_id' => 'GTM-P2NJJJTV',
] as $k => $v) {
    if ((string)Settings::get($k, '') === '') Settings::set($k, $v);
}
DB::update('stores', ['address' => 'вул. Сержа Лифаря, 4', 'city' => 'Київ', 'phone' => '+38 (063) 819-55-77', 'hours' => 'Щодня 8:00–20:00'], 'slug = ?', ['pasika-medoizhka']);
echo "Контент: готово\n";
