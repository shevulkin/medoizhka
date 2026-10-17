<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Images, Redirects;

/**
 * Розділи каталогу.
 *
 * Розділ може лежати всередині іншого — рівно на один крок: «Мед» → «Липовий».
 * Глибше не пускаємо навмисно, і тримає це саме тут, а не база: панель каталогу
 * розгортається один раз, і третій рівень покупець побачив би лише як список
 * без початку. Тому підрозділ не можна зробити батьком, а розділ із підрозділами
 * не можна вкласти в чужий.
 */
class Categories
{
    public static function index(): never
    {
        Auth::requireCap('catalog.manage');
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'edit_save') { self::saveOne((int)($_POST['id'] ?? 0)); }
            if ($action === 'image_remove') { self::removeImage((int)($_POST['id'] ?? 0)); }
            if ($action === 'add' && trim($_POST['name'] ?? '') !== '') {
                $name = trim($_POST['name']);
                $slug = slugify($name);
                $i = 1; $base = $slug;
                while (DB::row('SELECT 1 FROM categories WHERE slug = ?', [$slug])) $slug = $base . '-' . (++$i);
                // Батьком може бути лише розділ верхнього рівня
                $parent = DB::row('SELECT * FROM categories WHERE id = ? AND (parent_id IS NULL OR parent_id = 0)',
                    [(int)($_POST['parent_id'] ?? 0)]);
                DB::insert('categories', [
                    'name' => $name, 'slug' => $slug,
                    'parent_id' => $parent ? (int)$parent['id'] : null,
                    // Тип підрозділу — батьківський: «Курси» всередині «Товарів»
                    // означало б, що розділ показує те, чим не є.
                    // Вид категорії — Catalog::KINDS (товари, послуги, курси).
                    'type' => $parent ? $parent['type'] : \Catalog::normKind($_POST['type'] ?? null),
                    'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM categories'), 'active' => 1,
                ]);
                flash('success', $parent ? 'Підрозділ додано в «' . $parent['name'] . '»' : 'Категорію додано');
            }
            if ($action === 'save') {
                $errors = [];
                // Ким кому доводяться нинішні категорії — одним запитом:
                // перевірок нижче кілька, і кожна питала б базу окремо
                $parentOf = $hasKids = [];
                foreach (DB::all('SELECT id, parent_id FROM categories') as $r) {
                    $parentOf[(int)$r['id']] = (int)($r['parent_id'] ?? 0);
                    if ($r['parent_id']) $hasKids[(int)$r['parent_id']] = true;
                }
                // Позначені на видалення — окремим списком: розділ прибирають
                // разом із підрозділами, а вони стоять у формі нижче за нього.
                // Порядково це впиралось би в «спершу приберіть підрозділи» на
                // тому, що людина в тій самій формі й позначила.
                $del = [];
                foreach ((array)($_POST['cat'] ?? []) as $id => $c) {
                    if (!empty($c['_delete'])) $del[(int)$id] = trim($c['name'] ?? '');
                }
                foreach ($del as $id => $name) {
                    $kids = array_keys($parentOf, $id, true);
                    $left = array_diff($kids, array_keys($del));
                    $cnt = (int)DB::val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
                    // Підрозділи не осиротюємо: без цього вони лишились би з
                    // посиланням у нікуди й піднялись би у верхній ряд панелі
                    if ($left) { $errors[] = '«' . $name . '» не видалено: спершу приберіть її підрозділи.'; unset($del[$id]); }
                    elseif ($cnt > 0) { $errors[] = '«' . $name . '» не видалено: у ній є товари.'; unset($del[$id]); }
                }
                // Спершу підрозділи, потім розділи: інакше видалення батька на
                // мить лишає дитину без нього
                uksort($del, fn($a, $b) => ($parentOf[$b] ?? 0) <=> ($parentOf[$a] ?? 0));
                foreach (array_keys($del) as $id) DB::delete('categories', 'id = ?', [$id]);

                foreach ((array)($_POST['cat'] ?? []) as $id => $c) {
                    $id = (int)$id;
                    $name = trim($c['name'] ?? '');
                    if (!empty($c['_delete'])) continue;   // розібрано вище
                    $was = $parentOf[$id] ?? 0;
                    $parent = (int)($c['parent_id'] ?? 0);
                    if ($parent === $id) {
                        $errors[] = '«' . $name . '»: розділ не може лежати сам у собі.';
                        $parent = $was;
                    } elseif ($parent && !isset($parentOf[$parent])) {
                        $parent = $was;   // категорію щойно видалили в цій же формі
                    } elseif ($parent && $parentOf[$parent]) {
                        $errors[] = '«' . $name . '»: підрозділ не може мати власних підрозділів — оберіть розділ верхнього рівня.';
                        $parent = $was;
                    } elseif ($parent && !empty($hasKids[$id])) {
                        $errors[] = '«' . $name . '»: у неї є свої підрозділи, тому вкласти її в інший розділ не можна.';
                        $parent = $was;
                    }
                    DB::update('categories', [
                        'name' => $name, 'sort' => (int)($c['sort'] ?? 0),
                        'parent_id' => $parent ?: null,
                        'active' => !empty($c['active']) ? 1 : 0,
                    ], 'id = ?', [$id]);
                }
                if ($errors) flash('error', implode(' ', $errors));
                else flash('success', 'Збережено');
            }
            redirect('/admin/categories');
        }
        $editId = (int)($_GET['edit'] ?? 0);
        if ($editId && ($one = DB::row('SELECT * FROM categories WHERE id = ?', [$editId]))) {
            View::show('admin/category_edit', ['c' => $one, 'page_title' => 'Категорія «' . $one['name'] . '» — Адмінпанель'], 'layouts/admin');
            exit;
        }
        $cats = DB::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt
                         FROM categories c ORDER BY c.sort, c.id');
        /*
         * Опту тут більше немає.
         *
         * Колонка зі шкалою була додана з думкою «шукати її приходять сюди» —
         * і саме вона зробила сторінку про щось інше: рядок «від 10 шт −5%,
         * від 50 шт −10%» у кожній категорії разом із довгою довідкою і
         * посиланням «змінити» переважив те, заради чого в категорії заходять
         * — назву, вкладеність, порядок, видимість.
         *
         * Редагувати опт звідси все одно було не можна: посилання вело в
         * «Оптові знижки». А там та сама шкала показана повніше — усі разом і
         * зі скількома товарами кожна справді працює. Тобто колонка не
         * economила навіть кліку, лише забирала місце.
         */
        View::show('admin/categories', [
            'cats' => self::treeOrder($cats),
            'page_title' => 'Категорії — Адмінпанель',
        ], 'layouts/admin');
    }

    /** Одна категорія: адреса, опис, SEO, фото */
    private static function saveOne(int $id): never
    {
        $old = DB::row('SELECT * FROM categories WHERE id = ?', [$id]);
        if (!$old) redirect('/admin/categories');
        $back = '/admin/categories?edit=' . $id;
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash('error', 'Назва не може бути порожньою'); redirect($back); }
        // Адреса вже могла потрапити в пошук, тож при збереженні її не перераховуємо з назви.
        // Змінюється лише коли її явно вписали; тоді стара адреса отримує 301 на нову.
        $slug = (string)$old['slug'];
        $want = trim((string)($_POST['slug'] ?? ''));
        if ($want !== '' && $want !== $slug) {
            $new = slugify($want);   // кирилицю й пробіли перетворює на латиницю з дефісами
            $base = $new; $i = 1;
            while (DB::row('SELECT 1 FROM categories WHERE slug = ? AND id <> ?', [$new, $id])) $new = $base . '-' . (++$i);
            if ($new !== $slug) {
                Redirects::add('/product-category/' . $slug, '/product-category/' . slug_enc($new) . '/');
                $slug = $new;
            }
        }
        $data = [
            'name' => $name, 'slug' => $slug,
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'seo_title' => trim((string)($_POST['seo_title'] ?? '')) ?: null,
            'seo_desc' => trim((string)($_POST['seo_desc'] ?? '')) ?: null,
        ];
        $note = '';
        if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $res = Images::saveUpload($_FILES['image'], 'cat');
            if ($res) { self::dropImageFile($id, $res[0]); $data['image'] = $res[0]; }
            else $note = ' Фото не завантажилось: потрібен JPEG, PNG, GIF або WebP до 15 МБ.';
        }
        DB::update('categories', $data, 'id = ?', [$id]);
        flash($note ? 'error' : 'success', 'Збережено.' . $note);
        redirect($back);
    }

    private static function removeImage(int $id): never
    {
        if ($id) {
            self::dropImageFile($id, null);
            DB::update('categories', ['image' => null], 'id = ?', [$id]);
            flash('success', 'Фото прибрано');
        }
        redirect('/admin/categories?edit=' . $id);
    }

    /** Старий файл видаляємо лише якщо його більше ніде не використано */
    private static function dropImageFile(int $id, ?string $keep): void
    {
        $old = (string)(DB::val('SELECT image FROM categories WHERE id = ?', [$id]) ?? '');
        if ($old === '' || $old === $keep) return;
        DB::update('categories', ['image' => null], 'id = ?', [$id]);
        Media::resetUsage();
        if (!Media::usage($old)) Images::delete($old);
    }

    /**
     * Список у порядку дерева: підрозділ одразу під своїм розділом, із `depth`.
     *
     * Тут, на відміну від вітрини (Catalog::categories), показуємо й вимкнені —
     * адмінка має бачити весь каталог, зокрема схований від покупця.
     */
    private static function treeOrder(array $cats): array
    {
        $byParent = [];
        $ids = array_map(fn($c) => (int)$c['id'], $cats);
        foreach ($cats as $c) {
            $pid = (int)($c['parent_id'] ?? 0);
            $byParent[in_array($pid, $ids, true) ? $pid : 0][] = $c;
        }
        $out = [];
        foreach ($byParent[0] ?? [] as $root) {
            $root['depth'] = 0;
            $out[] = $root;
            foreach ($byParent[(int)$root['id']] ?? [] as $kid) {
                $kid['depth'] = 1;
                $out[] = $kid;
            }
        }
        return $out;
    }
}
