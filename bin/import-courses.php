<?php
declare(strict_types=1);
/**
 * Курси й уроки зі старого сайту (migration/courses.json).
 *
 *   php bin/import-courses.php
 *
 * Курс стає товаром type = course зі СТАРИМ кореневим слагом (/volynec/, /dukarev/,
 * /bilko-group-03/), тож посилання, які людям уже розіслано, лишаються робочими.
 * Ціну не чіпаємо, якщо вона вже виставлена: її задає адмін у картці курсу.
 * Повторний запуск оновлює назви й порядок уроків, прогрес і нотатки не торкається.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$data = json_decode(file_get_contents(BOFU_ROOT . '/migration/courses.json'), true);
Settings::set('bunny_library_id', (string)$data['bunny_library_id']);

$cat = DB::row("SELECT id FROM categories WHERE slug = 'kurs-bdzhilnictva'");
$catId = $cat ? (int)$cat['id'] : DB::insert('categories', [
    'name' => 'Курси бджільництва', 'slug' => 'kurs-bdzhilnictva', 'type' => 'course', 'sort' => 50, 'active' => 1]);

foreach ($data['courses'] as $i => $c) {
    $row = DB::row('SELECT id FROM products WHERE slug = ?', [$c['slug']]);
    $fields = [
        'category_id' => $catId, 'name' => $c['title'], 'type' => 'course', 'active' => 1, 'featured' => 1,
        'made_to_order' => 1, 'wp_id' => $c['wp_post_id'], 'updated_at' => now(),
    ];
    if ($row) { DB::update('products', $fields, 'id = ?', [$row['id']]); $pid = (int)$row['id']; }
    else $pid = DB::insert('products', $fields + ['slug' => $c['slug'], 'base_price' => null, 'created_at' => now()]);

    $keep = [];
    foreach ($c['lessons'] as $n => [$guid, $title]) {
        $l = DB::row('SELECT id FROM course_lessons WHERE product_id = ? AND guid = ?', [$pid, $guid]);
        $f = ['title' => $title, 'sort' => $n + 1];
        if ($l) { DB::update('course_lessons', $f, 'id = ?', [$l['id']]); $keep[] = (int)$l['id']; }
        else $keep[] = DB::insert('course_lessons', $f + ['product_id' => $pid, 'guid' => $guid, 'free_preview' => 0]);
    }
    echo "{$c['slug']}: " . count($keep) . " уроків\n";
}
