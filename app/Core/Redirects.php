<?php
declare(strict_types=1);

/**
 * Постійні переадресації (301) зі старих адрес на нові.
 *
 * Коли в адмінці змінюється адреса сторінки, що вже є в пошуку, стара не має віддавати 404:
 * сюди записується пара «звідки → куди», а Controllers\Site::dispatch() перевіряє таблицю
 * першою. from_path зберігається розкодованим і без скісної в кінці — так його й порівнюємо.
 */
class Redirects
{
    public static function add(string $from, string $to): void
    {
        $from = rtrim(rawurldecode($from), '/') ?: '/';
        if ($from === rtrim(rawurldecode($to), '/')) return;
        // ланцюжки не плодимо: усе, що вело на стару адресу, тепер веде одразу на нову
        DB::update('redirects', ['to_path' => $to], 'to_path = ? OR to_path = ?', [$from, $from . '/']);
        $row = DB::row('SELECT id FROM redirects WHERE from_path = ?', [$from]);
        if ($row) DB::update('redirects', ['to_path' => $to], 'id = ?', [$row['id']]);
        else DB::insert('redirects', ['from_path' => $from, 'to_path' => $to, 'hits' => 0]);
    }
}
