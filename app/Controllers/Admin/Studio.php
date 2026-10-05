<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Images, Courses, Lessons, Settings, Hub;

/**
 * Адмінпанель нових розділів Медоїжки: відеокурси (відео й учні), апітерапевти,
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
    /**
     * Усе про курс — на одній сторінці: основне (назва, ціна, строк, опубліковано),
     * відео (з бібліотеки Bunny галочками або вставкою посилань) і учні (кому відкрито,
     * хто скільки подивився). Без курсу в адресі — список курсів і «Новий відеокурс».
     */
    public static function lessons(): never
    {
        Auth::requireCap('content.manage');
        $cid = (int)($_GET['course'] ?? $_POST['course'] ?? 0);
        $course = $cid ? DB::row("SELECT * FROM products WHERE id = ? AND type = 'course'", [$cid]) : null;
        if ($cid && !$course) { flash('error', 'Курс не знайдено.'); self::back('/admin/lessons'); }
        $self = '/admin/lessons' . ($cid ? '?course=' . $cid : '');

        if (is_post()) {
            $a = (string)($_POST['_action'] ?? '');
            if ($a === 'bunny') {
                Settings::set('bunny_library_id', trim((string)($_POST['library'] ?? '')));
                foreach (['token_key' => 'bunny_token_key', 'api_key' => 'bunny_api_key'] as $f => $k) {
                    if (trim((string)($_POST[$f] ?? '')) !== '') Settings::set($k, trim((string)$_POST[$f]));
                }
                flash('success', 'Налаштування Bunny збережено.');
                self::back('/admin/lessons');
            }
            if ($a === 'feature') {
                // Розділ на сайті: меню «Відеокурси», сторінки курсів, «Мої відеокурси». Персонал бачить завжди.
                Settings::set('feature_courses', !empty($_POST['on']) ? '1' : '0');
                flash('success', !empty($_POST['on']) ? 'Відеокурси тепер видно покупцям.' : 'Відеокурси сховано з сайту (вам їх видно й далі).');
                self::back('/admin/lessons');
            }
            if ($a === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') { flash('error', 'Вкажіть назву курсу.'); self::back('/admin/lessons'); }
                $cat = (int)(DB::val("SELECT id FROM categories WHERE type = 'course' ORDER BY active DESC, id LIMIT 1") ?: 0);
                if (!$cat) $cat = (int)DB::insert('categories', ['name' => 'Відеокурси', 'slug' => self::slug('categories', 'videokursy'),
                    'type' => 'course', 'active' => 1, 'sort' => 99]);
                // Курс живе за кореневою адресою (/slug/), тож slug не має збігатись і зі сторінками
                $slug = self::slug('products', $name);
                while (DB::row('SELECT id FROM pages WHERE slug = ?', [$slug])) $slug .= '-kurs';
                $id = (int)DB::insert('products', ['name' => $name, 'slug' => $slug, 'type' => 'course', 'category_id' => $cat,
                    'active' => 0, 'made_to_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
                flash('success', 'Курс створено як чернетку. Додайте відео й ціну, потім позначте «Опубліковано».');
                self::back('/admin/lessons?course=' . $id);
            }
            if (!$course) self::back('/admin/lessons');

            switch ($a) {
                case 'basic':
                    $name = trim((string)($_POST['name'] ?? '')) ?: $course['name'];
                    $price = trim((string)($_POST['price'] ?? ''));
                    $days = trim((string)($_POST['access_days'] ?? ''));
                    DB::update('products', ['name' => $name, 'base_price' => $price === '' ? null : max(0, (float)$price),
                        'access_days' => $days === '' ? null : max(1, (int)$days), 'active' => isset($_POST['active']) ? 1 : 0,
                        'updated_at' => now()], 'id = ?', [$cid]);
                    flash('success', 'Збережено.');
                    break;

                case 'add_lines':
                case 'add_pick':
                    if ($a === 'add_pick') {
                        $items = [];
                        foreach ((array)($_POST['pick'] ?? []) as $guid) {
                            $guid = strtolower((string)$guid);
                            if (!preg_match('~^[0-9a-f-]{36}$~', $guid)) continue;
                            $items[] = ['guid' => $guid, 'title' => trim((string)($_POST['ptitle'][$guid] ?? '')),
                                        'length' => (int)($_POST['plen'][$guid] ?? 0)];
                        }
                    } else {
                        $items = Lessons::parseVideoLines((string)($_POST['lines'] ?? ''));
                    }
                    $n = (int)DB::val('SELECT COALESCE(MAX(sort),0) FROM course_lessons WHERE product_id = ?', [$cid]);
                    $added = 0; $dupes = 0;
                    foreach ($items as $it) {
                        if (DB::row('SELECT id FROM course_lessons WHERE product_id = ? AND guid = ?', [$cid, $it['guid']])) { $dupes++; continue; }
                        $len = $it['length'] ?? 0;
                        if ($it['title'] === '' || !$len) {   // назву й тривалість підтягуємо з Bunny, якщо є ключ API
                            $info = Lessons::videoInfo($it['guid']);
                            if ($info) { $it['title'] = $it['title'] !== '' ? $it['title'] : $info['title']; $len = $len ?: $info['length']; }
                        }
                        DB::insert('course_lessons', ['product_id' => $cid, 'guid' => $it['guid'],
                            'title' => $it['title'] !== '' ? $it['title'] : 'Відео ' . ($n + 1),
                            'sort' => ++$n, 'free_preview' => 0, 'duration' => $len ?: null]);
                        $added++;
                    }
                    flash($added ? 'success' : 'error', $added
                        ? 'Додано відео: ' . $added . ($dupes ? " (ще $dupes вже були в курсі)" : '')
                        : ($dupes ? 'Ці відео вже є в курсі.' : 'Не знайшла жодного відео. Вставте посилання з Bunny чи guid — по одному в рядку.'));
                    break;

                case 'save':
                    foreach ((array)($_POST['l'] ?? []) as $id => $d) {
                        DB::update('course_lessons', ['title' => trim((string)($d['title'] ?? '')) ?: 'Відео',
                            'free_preview' => !empty($d['free']) ? 1 : 0], 'id = ? AND product_id = ?', [(int)$id, $cid]);
                    }
                    flash('success', 'Збережено.');
                    break;

                case 'move':
                    // Переставити на одну позицію: спершу нумеруємо 1..N, потім міняємо з сусідом
                    $ids = array_map(fn($l) => (int)$l['id'], Lessons::forCourse($cid));
                    $i = array_search((int)($_POST['id'] ?? 0), $ids, true);
                    $j = $i === false ? false : $i + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
                    if ($i !== false && isset($ids[$j])) { [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; }
                    foreach ($ids as $k => $lid) DB::update('course_lessons', ['sort' => $k + 1], 'id = ?', [$lid]);
                    self::back($self . '#videos');

                case 'delete':
                    $id = (int)($_POST['id'] ?? 0);
                    if (DB::row('SELECT id FROM course_lessons WHERE id = ? AND product_id = ?', [$id, $cid])) {
                        DB::delete('lesson_progress', 'lesson_id = ?', [$id]);
                        DB::delete('lesson_notes', 'lesson_id = ?', [$id]);
                        DB::delete('course_lessons', 'id = ?', [$id]);
                        flash('success', 'Відео прибрано з курсу разом із прогресом і нотатками до нього.');
                    }
                    self::back($self . '#videos');

                case 'grant':
                    $days = trim((string)($_POST['days'] ?? ''));
                    $days = $days === '' ? ($course['access_days'] ?? null) : max(1, (int)$days);
                    $ok = []; $bad = [];
                    foreach (preg_split('~[\s,;]+~u', mb_strtolower((string)($_POST['emails'] ?? ''))) as $email) {
                        if ($email === '') continue;
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $bad[] = $email; continue; }
                        $u = DB::row('SELECT id FROM users WHERE email = ?', [$email]);
                        // Акаунт заводимо наперед: людина ще могла не заходити, а доступ має чекати на неї
                        $uid = $u ? (int)$u['id'] : (int)DB::insert('users', ['email' => $email, 'name' => strstr($email, '@', true),
                            'role' => 'customer', 'active' => 1, 'created_at' => now()]);
                        Courses::grant($uid, $cid, null, $days);
                        $ok[] = $email;
                    }
                    if ($ok) flash('success', 'Доступ відкрито: ' . count($ok) . '. Людина входить на сайт цією поштою (код приходить на неї) і бачить курс у «Мої відеокурси».');
                    if ($bad) flash('error', 'Не схоже на пошту: ' . implode(', ', array_slice($bad, 0, 5)));
                    if (!$ok && !$bad) flash('error', 'Вкажіть хоча б одну пошту.');
                    self::back($self . '#students');

                case 'forever':
                    DB::update('course_access', ['expires_at' => null], 'id = ? AND product_id = ?', [(int)($_POST['id'] ?? 0), $cid]);
                    flash('success', 'Доступ тепер безстроковий.');
                    self::back($self . '#students');

                case 'revoke':
                    DB::delete('course_access', 'id = ? AND product_id = ?', [(int)($_POST['id'] ?? 0), $cid]);
                    flash('success', 'Доступ закрито. Прогрес і нотатки людини лишились — відкриєте знову, і вона продовжить з того ж місця.');
                    self::back($self . '#students');
            }
            self::back($self);
        }

        if (!$course) {
            View::show('admin/studio/courses', [
                'courses' => DB::all("SELECT p.*, (SELECT COUNT(*) FROM course_lessons l WHERE l.product_id = p.id) AS videos,
                                             (SELECT COALESCE(SUM(l.duration),0) FROM course_lessons l WHERE l.product_id = p.id) AS secs,
                                             (SELECT COUNT(*) FROM course_access a WHERE a.product_id = p.id) AS students
                                      FROM products p WHERE p.type = 'course' ORDER BY p.active DESC, p.name"),
                'bunny' => ['library' => Lessons::libraryId(), 'has_key' => Lessons::configured(), 'has_api' => Lessons::apiKey() !== ''],
                'page_title' => 'Відеокурси — Адмінпанель',
            ], 'layouts/admin');
        }

        $lessons = Lessons::forCourse($cid);
        $progress = [];
        foreach (DB::all('SELECT lp.user_id, SUM(lp.watched) AS watched, MAX(lp.updated_at) AS last FROM lesson_progress lp
                          JOIN course_lessons cl ON cl.id = lp.lesson_id WHERE cl.product_id = ? GROUP BY lp.user_id', [$cid]) as $r) {
            $progress[(int)$r['user_id']] = $r;
        }
        // Бібліотека Bunny — лише коли її відкрили (запит до Bunny не безкоштовний за часом)
        $library = isset($_GET['pick']) ? Lessons::libraryVideos() : null;
        View::show('admin/studio/lessons', [
            'course' => $course, 'cid' => $cid, 'lessons' => $lessons, 'progress' => $progress,
            'students' => DB::all('SELECT a.*, u.email, u.name AS uname, o.number AS order_number FROM course_access a
                                   JOIN users u ON u.id = a.user_id LEFT JOIN orders o ON o.id = a.order_id
                                   WHERE a.product_id = ? ORDER BY a.granted_at DESC', [$cid]),
            'library' => $library, 'has_api' => Lessons::apiKey() !== '',
            'added' => array_flip(array_map(fn($l) => strtolower($l['guid']), $lessons)),
            'page_title' => $course['name'] . ' — Відеокурси — Адмінпанель',
        ], 'layouts/admin');
    }

    // ---------- Послуги ----------
    /**
     * Усі послуги в одній таблиці: ціна, «доступна зараз», «на сайті» — правляться прямо тут.
     * Послуга — це товар із позначкою service (без складу, замовляють за телефоном), тож опис
     * і фото — у картці товару, але щоденне (ціна, пауза) — тут, без пошуку по каталогу.
     */
    public static function services(): never
    {
        Auth::requireCap('products.manage');
        if (is_post()) {
            $a = (string)($_POST['_action'] ?? '');
            if ($a === 'create') {
                $name = trim((string)($_POST['name'] ?? ''));
                $cat = (int)($_POST['category_id'] ?? 0);
                if ($name === '' || !DB::row("SELECT id FROM categories WHERE id = ? AND type = 'service'", [$cat])) {
                    flash('error', 'Вкажіть назву й розділ.'); self::back('/admin/services');
                }
                $price = trim((string)($_POST['price'] ?? ''));
                $id = (int)DB::insert('products', ['name' => $name, 'slug' => self::slug('products', $name), 'type' => 'product',
                    'category_id' => $cat, 'service' => 1, 'paused' => 0, 'made_to_order' => 0, 'active' => 1,
                    'base_price' => $price === '' ? null : max(0, (float)$price), 'created_at' => now(), 'updated_at' => now()]);
                flash('success', 'Послугу додано й показано на сайті. Опис і фото — кнопкою «Опис і фото».');
                self::back('/admin/services#s' . $id);
            }
            if ($a === 'save') {
                foreach ((array)($_POST['s'] ?? []) as $id => $d) {
                    $p = DB::row('SELECT id, paused FROM products WHERE id = ? AND service = 1', [(int)$id]);
                    if (!$p) continue;
                    $price = trim((string)($d['price'] ?? ''));
                    $paused = empty($d['available']) ? 1 : 0;
                    DB::update('products', ['base_price' => $price === '' ? null : max(0, (float)$price),
                        'paused' => $paused, 'active' => !empty($d['active']) ? 1 : 0, 'updated_at' => now()], 'id = ?', [(int)$id]);
                    // знову доступна — тим, хто натискав «Повідомити, коли відновимо», приходить сповіщення
                    if ((int)$p['paused'] === 1 && $paused === 0) \StockWatch::fulfil((int)$id, null);
                }
                flash('success', 'Збережено.');
                self::back('/admin/services');
            }
            self::back('/admin/services');
        }
        View::show('admin/studio/services', [
            'rows' => DB::all("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
                               WHERE p.service = 1 ORDER BY p.active DESC, c.sort, p.name"),
            'cats' => DB::all("SELECT id, name, slug FROM categories WHERE type = 'service' ORDER BY sort, name"),   // у послуг свої категорії
            'page_title' => 'Послуги — Адмінпанель',
        ], 'layouts/admin');
    }

    // ---------- Доступ до курсів ----------
    /** Окремої сторінки більше немає: учні кожного курсу — на сторінці самого курсу */
    public static function access(): never
    {
        Auth::requireCap('content.manage');
        self::back('/admin/lessons');
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
            $upd = [];
            if (in_array($st, ['new', 'confirmed', 'done', 'cancelled'], true)) $upd['status'] = $st;
            // Узгоджена ціна й нотатка — після уточнень із клієнтом (порожня ціна = ще не узгоджено)
            if (array_key_exists('price', $_POST)) {
                $pr = str_replace([' ', ','], ['', '.'], trim((string)$_POST['price']));
                $upd['price'] = ($pr !== '' && is_numeric($pr) && (float)$pr >= 0) ? round((float)$pr, 2) : null;
            }
            if (array_key_exists('note', $_POST)) $upd['admin_note'] = mb_substr(trim((string)$_POST['note']), 0, 2000) ?: null;
            if ($upd) DB::update('bookings', $upd, 'id = ?', [(int)($_POST['id'] ?? 0)]);
            flash('success', 'Збережено');
            self::back('/admin/bookings');
        }
        $rows = DB::all('SELECT * FROM bookings ORDER BY (status = \'new\') DESC, id DESC LIMIT 300');
        foreach ($rows as &$r) {
            $t = $r['kind'] === 'place' ? 'places' : ($r['kind'] === 'service' ? 'products' : 'practitioners');
            $r['subject'] = (string)DB::val("SELECT name FROM $t WHERE id = ?", [$r['ref_id']]);
        }
        View::show('admin/studio/bookings', ['rows' => $rows, 'page_title' => 'Бронювання — Адмінпанель'], 'layouts/admin');
    }
}
