<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Images, Courses, Lessons, Settings, Hub;

/**
 * Адмінка нових розділів Медоїжки: уроки курсів і доступ, апітерапевти,
 * пасіки й апібудиночки, заявки. Один контролер, бо всі екрани однакового
 * складу: список зверху, форма правки під ним, POST із полем _action.
 */
class Studio
{
    private static function back(string $to): never { redirect($to); }

    private static function slug(string $table, string $name, ?int $exceptId = null): string
    {
        $base = slugify($name) ?: 'item';
        $slug = $base; $i = 2;
        while (DB::row("SELECT id FROM $table WHERE slug = ?" . ($exceptId ? ' AND id <> ' . $exceptId : ''), [$slug])) $slug = $base . '-' . $i++;
        return $slug;
    }

    private static function photo(string $prefix): ?string
    {
        if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $res = Images::saveUpload($_FILES['photo'], $prefix);
        if (!$res) { flash('error', 'Фото не завантажилось: потрібен JPEG, PNG або WebP до 15 МБ.'); return null; }
        return $res[0];
    }

    // ---------- Відеокурси ----------
    public static function lessons(): never
    {
        Auth::requireCap('content.manage');
        $courses = DB::all("SELECT id, name, slug FROM products WHERE type = 'course' ORDER BY name");
        $cid = (int)($_GET['course'] ?? $_POST['course'] ?? ($courses[0]['id'] ?? 0));
        $self = '/admin/lessons?course=' . $cid;

        if (is_post()) {
            $a = $_POST['_action'] ?? '';
            if ($a === 'bunny') {
                Settings::set('bunny_library_id', trim((string)($_POST['library'] ?? '')));
                if (trim((string)($_POST['token_key'] ?? '')) !== '') Settings::set('bunny_token_key', trim((string)$_POST['token_key']));
                flash('success', 'Налаштування Bunny збережено.');
            } elseif ($a === 'add' && $cid) {
                // Один рядок — один урок: «guid | назва». Так вставляється весь список із Bunny за раз
                $n = (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM course_lessons WHERE product_id = ?', [$cid]);
                $added = 0;
                foreach (preg_split('~\R+~', (string)($_POST['lines'] ?? '')) as $line) {
                    $parts = array_map('trim', explode('|', $line, 2));
                    if (!preg_match('~^[0-9a-f-]{32,36}$~i', $parts[0] ?? '')) continue;
                    if (DB::row('SELECT id FROM course_lessons WHERE product_id = ? AND guid = ?', [$cid, $parts[0]])) continue;
                    DB::insert('course_lessons', ['product_id' => $cid, 'guid' => $parts[0],
                        'title' => $parts[1] ?? ('Відео ' . ($n + 1)), 'sort' => ++$n, 'free_preview' => 0]);
                    $added++;
                }
                flash($added ? 'success' : 'error', $added ? "Додано відео: $added" : 'Не знайдено жодного guid відео. Формат рядка: guid | Назва');
            } elseif ($a === 'save') {
                foreach ((array)($_POST['l'] ?? []) as $id => $d) {
                    DB::update('course_lessons', [
                        'title' => trim((string)($d['title'] ?? '')) ?: 'Відео',
                        'sort' => (int)($d['sort'] ?? 0),
                        'free_preview' => !empty($d['free']) ? 1 : 0,
                        'description' => trim((string)($d['description'] ?? '')) ?: null,
                    ], 'id = ? AND product_id = ?', [(int)$id, $cid]);
                }
                flash('success', 'Збережено.');
            } elseif ($a === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                DB::delete('lesson_progress', 'lesson_id = ?', [$id]);
                DB::delete('lesson_notes', 'lesson_id = ?', [$id]);
                DB::delete('course_lessons', 'id = ? AND product_id = ?', [$id, $cid]);
                flash('success', 'Відео видалено разом із прогресом і нотатками.');
            } elseif ($a === 'price') {
                $price = trim((string)($_POST['price'] ?? ''));
                $days = trim((string)($_POST['access_days'] ?? ''));
                DB::update('products', ['base_price' => $price === '' ? null : (float)$price,
                    'access_days' => $days === '' ? null : (int)$days, 'updated_at' => now()], 'id = ?', [$cid]);
                flash('success', 'Ціну й строк доступу збережено.');
            }
            self::back($self);
        }
        View::show('admin/studio/lessons', [
            'courses' => $courses, 'cid' => $cid,
            'course' => $cid ? DB::row('SELECT * FROM products WHERE id = ?', [$cid]) : null,
            'lessons' => $cid ? Lessons::forCourse($cid) : [],
            'bunny' => ['library' => Lessons::libraryId(), 'has_key' => Lessons::configured()],
            'page_title' => 'Відеокурси — Адмінпанель',
        ], 'layouts/admin');
    }

    // ---------- Доступ до курсів ----------
    public static function access(): never
    {
        Auth::requireCap('users.manage');
        if (is_post()) {
            $a = $_POST['_action'] ?? '';
            if ($a === 'grant') {
                $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
                $course = (int)($_POST['course'] ?? 0);
                $days = trim((string)($_POST['days'] ?? ''));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$course) { flash('error', 'Вкажіть пошту й курс.'); self::back('/admin/access'); }
                $u = DB::row('SELECT id FROM users WHERE email = ?', [$email]);
                // Акаунт заводимо наперед: людина ще могла не заходити, а доступ має чекати на неї
                $uid = $u ? (int)$u['id'] : DB::insert('users', ['email' => $email, 'name' => strstr($email, '@', true), 'role' => 'customer', 'active' => 1, 'created_at' => now()]);
                Courses::grant($uid, $course, null, $days === '' ? null : (int)$days);
                flash('success', "Доступ відкрито для $email.");
            } elseif ($a === 'revoke') {
                DB::delete('course_access', 'id = ?', [(int)($_POST['id'] ?? 0)]);
                flash('success', 'Доступ закрито. Прогрес і нотатки людини лишились.');
            }
            self::back('/admin/access');
        }
        View::show('admin/studio/access', [
            'courses' => DB::all("SELECT id, name FROM products WHERE type = 'course' ORDER BY name"),
            'rows' => DB::all("SELECT a.*, u.email, u.name AS uname, p.name AS course FROM course_access a
                               JOIN users u ON u.id = a.user_id JOIN products p ON p.id = a.product_id
                               ORDER BY a.granted_at DESC LIMIT 300"),
            'page_title' => 'Доступ до відеокурсів — Адмінпанель',
        ], 'layouts/admin');
    }

    // ---------- Апітерапевти ----------
    public static function practitioners(): never
    {
        Auth::requireCap('content.manage');
        if (is_post()) {
            $a = $_POST['_action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
            if ($a === 'delete') { DB::delete('practitioners', 'id = ?', [$id]); flash('success', 'Видалено.'); self::back('/admin/practitioners'); }
            if ($a === 'save') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') { flash('error', 'Вкажіть імʼя.'); self::back('/admin/practitioners' . ($id ? "?id=$id" : '')); }
                $d = [
                    'name' => $name, 'title' => trim((string)$_POST['title']) ?: null, 'city' => trim((string)$_POST['city']) ?: null,
                    'region' => trim((string)$_POST['region']) ?: null, 'bio' => trim((string)$_POST['bio']) ?: null,
                    'specialties' => trim((string)$_POST['specialties']) ?: null, 'services' => trim((string)$_POST['services']) ?: null,
                    'price_from' => $_POST['price_from'] !== '' ? (int)$_POST['price_from'] : null,
                    'phone' => trim((string)$_POST['phone']) ?: null, 'telegram' => trim((string)$_POST['telegram']) ?: null,
                    'online' => !empty($_POST['online']) ? 1 : 0, 'verified' => !empty($_POST['verified']) ? 1 : 0,
                    'active' => !empty($_POST['active']) ? 1 : 0, 'sort' => (int)($_POST['sort'] ?? 0),
                ];
                if ($ph = self::photo('pract')) $d['photo'] = $ph;
                if ($id) { DB::update('practitioners', $d, 'id = ?', [$id]); }
                else { $d['slug'] = self::slug('practitioners', $name); $d['created_at'] = now(); $id = DB::insert('practitioners', $d); }
                flash('success', 'Збережено.');
                self::back('/admin/practitioners?id=' . $id);
            }
        }
        $id = (int)($_GET['id'] ?? 0);
        View::show('admin/studio/practitioners', [
            'rows' => DB::all('SELECT * FROM practitioners ORDER BY active DESC, sort, name'),
            'edit' => $id ? DB::row('SELECT * FROM practitioners WHERE id = ?', [$id]) : (isset($_GET['new']) ? [] : null),
            'page_title' => 'Апітерапевти — Адмінпанель',
        ], 'layouts/admin');
    }

    // ---------- Пасіки й апібудиночки ----------
    public static function places(): never
    {
        Auth::requireCap('content.manage');
        if (is_post()) {
            $a = $_POST['_action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
            if ($a === 'delete') { DB::delete('places', 'id = ?', [$id]); flash('success', 'Видалено.'); self::back('/admin/places'); }
            if ($a === 'save') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') { flash('error', 'Вкажіть назву.'); self::back('/admin/places' . ($id ? "?id=$id" : '')); }
                $kind = isset(Hub::PLACE_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'apiary';
                $d = [
                    'kind' => $kind, 'name' => $name, 'city' => trim((string)$_POST['city']) ?: null, 'region' => trim((string)$_POST['region']) ?: null,
                    'address' => trim((string)$_POST['address']) ?: null, 'summary' => trim((string)$_POST['summary']) ?: null,
                    'description' => trim((string)$_POST['description']) ?: null, 'amenities' => trim((string)$_POST['amenities']) ?: null,
                    'price' => $_POST['price'] !== '' ? (int)$_POST['price'] : null, 'price_note' => trim((string)$_POST['price_note']) ?: null,
                    'duration' => trim((string)$_POST['duration']) ?: null, 'capacity' => $_POST['capacity'] !== '' ? (int)$_POST['capacity'] : null,
                    'phone' => trim((string)$_POST['phone']) ?: null, 'active' => !empty($_POST['active']) ? 1 : 0, 'sort' => (int)($_POST['sort'] ?? 0),
                ];
                if ($ph = self::photo('place')) $d['photo'] = $ph;
                if ($id) { DB::update('places', $d, 'id = ?', [$id]); }
                else { $d['slug'] = self::slug('places', $name); $d['created_at'] = now(); $id = DB::insert('places', $d); }
                flash('success', 'Збережено.');
                self::back('/admin/places?id=' . $id);
            }
        }
        $id = (int)($_GET['id'] ?? 0);
        View::show('admin/studio/places', [
            'rows' => DB::all('SELECT * FROM places ORDER BY active DESC, sort, name'),
            'edit' => $id ? DB::row('SELECT * FROM places WHERE id = ?', [$id]) : (isset($_GET['new']) ? [] : null),
            'page_title' => 'Пасіки й апібудиночки — Адмінпанель',
        ], 'layouts/admin');
    }

    // ---------- Заявки ----------
    public static function bookings(): never
    {
        Auth::requireCap('content.manage');
        if (is_post()) {
            $st = $_POST['status'] ?? '';
            if (in_array($st, ['new', 'confirmed', 'done', 'cancelled'], true)) {
                DB::update('bookings', ['status' => $st], 'id = ?', [(int)($_POST['id'] ?? 0)]);
            }
            self::back('/admin/bookings');
        }
        $rows = DB::all('SELECT * FROM bookings ORDER BY (status = \'new\') DESC, id DESC LIMIT 300');
        foreach ($rows as &$r) {
            $t = $r['kind'] === 'place' ? 'places' : 'practitioners';
            $r['subject'] = (string)DB::val("SELECT name FROM $t WHERE id = ?", [$r['ref_id']]);
        }
        View::show('admin/studio/bookings', ['rows' => $rows, 'page_title' => 'Бронювання — Адмінпанель'], 'layouts/admin');
    }
}
