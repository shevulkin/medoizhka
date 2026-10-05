<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Images;

class Media
{
    /** @var array<string, list<array{label:string,url:string}>>|null карта «шлях фото → де воно стоїть», збирається один раз за запит */
    private static ?array $usageMap = null;

    /**
     * Де використовується фото: товари, бренди, партнери, категорії, сторінки, записи, апітерапевти, пасіки,
     * банери/фото сайту, галерея, а також картинки, вставлені прямо в тексти (опис товару, сторінки тощо).
     * Від цього залежить, чи можна фото видаляти: «не використовується» означає саме «ніде», тому
     * перелік має бути повним. Карту будуємо одним проходом по таблицях, а не запитом на кожне фото.
     */
    public static function usage(string $path): array
    {
        return (self::$usageMap ??= self::buildUsageMap())[$path] ?? [];
    }

    /** Скинути карту (після змін у тій самій відповіді) */
    public static function resetUsage(): void { self::$usageMap = null; }

    private static function buildUsageMap(): array
    {
        $map = [];
        $add = function (?string $path, string $label, string $url) use (&$map): void {
            $path = trim((string)$path);
            if ($path === '') return;
            // службові копії (-thumb, -md) рахуємо за оригіналом
            $path = preg_replace('~-(?:thumb|md)(\.\w+)$~', '$1', $path);
            foreach ($map[$path] ?? [] as $u) if ($u['label'] === $label) return;   // одне місце — один запис
            $map[$path][] = ['label' => $label, 'url' => $url];
        };
        $rows = function (string $sql): array {
            try { return DB::all($sql); } catch (\Throwable $e) { return []; }   // таблиці чи колонки може ще не бути
        };
        // шляхи фото, вставлені в текст як <img src=".../uploads/x.webp"> чи посилання
        $scan = function (?string $text, string $label, string $url) use ($add): void {
            if ($text === null || $text === '' || !str_contains($text, 'uploads/')) return;
            if (preg_match_all('~uploads/[A-Za-z0-9_\-./%]+?\.(?:webp|jpe?g|png|gif)~i', $text, $m)) {
                foreach (array_unique($m[0]) as $p) $add(rawurldecode($p), $label, $url);
            }
        };

        foreach ($rows('SELECT id, name, image, description, short_desc FROM products') as $p) {
            $u = url('/admin/products/' . $p['id']); $l = 'Товар: ' . $p['name'];
            $add($p['image'], $l, $u);
            $scan($p['description'] . ' ' . $p['short_desc'], $l . ' (в тексті)', $u);
        }
        foreach ($rows('SELECT pi.path, p.id, p.name FROM product_images pi JOIN products p ON p.id = pi.product_id') as $r) {
            $add($r['path'], 'Товар: ' . $r['name'], url('/admin/products/' . $r['id']));
        }
        foreach ($rows('SELECT name, logo FROM brands') as $r) $add($r['logo'], 'Лого бренду: ' . $r['name'], url('/admin/brands'));
        foreach ($rows('SELECT name, logo FROM partners') as $r) $add($r['logo'], 'Лого партнера: ' . $r['name'], url('/admin/partners'));
        foreach ($rows('SELECT id, name, image, description FROM categories') as $r) {
            $u = url('/admin/categories'); $add($r['image'], 'Категорія: ' . $r['name'], $u);
            $scan($r['description'], 'Категорія: ' . $r['name'] . ' (в тексті)', $u);
        }
        foreach ($rows('SELECT title, image, body FROM pages') as $r) {
            $u = url('/admin/content'); $add($r['image'], 'Сторінка: ' . $r['title'], $u);
            $scan($r['body'], 'Сторінка: ' . $r['title'] . ' (в тексті)', $u);
        }
        foreach ($rows('SELECT title, image, body FROM posts') as $r) {
            $u = url('/admin/content'); $add($r['image'], 'Запис: ' . $r['title'], $u);
            $scan($r['body'], 'Запис: ' . $r['title'] . ' (в тексті)', $u);
        }
        foreach ($rows('SELECT name, photo, bio FROM practitioners') as $r) {
            $u = url('/admin/practitioners'); $add($r['photo'], 'Апітерапевт: ' . $r['name'], $u);
            $scan($r['bio'], 'Апітерапевт: ' . $r['name'] . ' (в тексті)', $u);
        }
        foreach ($rows('SELECT name, photo, description FROM places') as $r) {
            $u = url('/admin/places'); $add($r['photo'], 'Пасіка: ' . $r['name'], $u);
            $scan($r['description'], 'Пасіка: ' . $r['name'] . ' (в тексті)', $u);
        }
        // банери й фото сайту; галереї та інші списки лежать у body (JSON чи текст), тож шукаємо шляхи й там
        foreach ($rows('SELECT `key`, image, body FROM content_blocks') as $r) {
            $u = url('/admin/content');
            $add($r['image'], 'Банер/фото сайту: ' . $r['key'], $u);
            $scan($r['body'], ($r['key'] === 'gallery' ? 'Галерея' : 'Текст сайту: ' . $r['key']), $u);
        }
        return $map;
    }
    /** Список усіх фото сайту (для сторінки і для вікна вибору) */
    public static function listAll(): array
    {
        $dir = cfg('uploads_dir');
        $items = [];
        // завантажені фото
        foreach (glob($dir . '/*') ?: [] as $f) {
            $name = basename($f);
            // -thumb і -md — службові зменшені копії, а не окремі фото: у бібліотеці їх не показуємо
            if (str_contains($name, '-thumb.') || str_contains($name, '-md.')) continue;
            $size = @getimagesize($f);
            $path = 'uploads/' . $name;
            $items[] = [
                'path' => $path, 'thumb' => 'uploads/' . preg_replace('/\.(\w+)$/', '-thumb.$1', $name),
                'width' => $size[0] ?? 0, 'height' => $size[1] ?? 0,
                'bytes' => filesize($f) ?: 0, 'mtime' => filemtime($f) ?: 0, 'builtin' => false,
                'usage' => self::usage($path),
            ];
        }
        usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        // вбудовані фото дизайну
        foreach (glob(BOFU_ROOT . '/assets/img/*.png') ?: [] as $f) {
            $size = @getimagesize($f);
            $items[] = [
                'path' => 'img/' . basename($f), 'thumb' => 'img/' . basename($f),
                'width' => $size[0] ?? 0, 'height' => $size[1] ?? 0,
                'bytes' => filesize($f) ?: 0, 'mtime' => 0, 'builtin' => true,
            ];
        }
        return $items;
    }

    public static function index(): never
    {
        // Медіа-бібліотека спільна для всього сайту (банери, галерея, фото товарів),
        // тому нею керує адмін — інакше продавець одного магазину міняє картинки всім.
        Auth::requireCap('media.manage');
        if (($_GET['format'] ?? '') === 'json') {
            json_response(['items' => self::listAll()]);
        }
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'upload') {
                $res = Images::saveUpload($_FILES['image'] ?? [], 'media');
                if ($res) {
                    [$path, $w, $h, $bytes] = $res;
                    if (($_POST['format'] ?? '') === 'json') json_response(['ok' => true, 'path' => $path, 'width' => $w, 'height' => $h, 'bytes' => $bytes]);
                    flash('success', "Фото додано ({$w}×{$h}, " . round($bytes/1024) . ' КБ)');
                } else {
                    if (($_POST['format'] ?? '') === 'json') json_response(['ok' => false], 422);
                    flash('error', 'Не вдалося завантажити фото');
                }
            }
            // Масове видалення: лише фото, які ніде не використовуються (повна перевірка в usage), і не свіжіші за добу —
            // щойно завантажене фото могли ще не встигнути прикріпити до товару.
            if ($action === 'delete_unused') {
                self::resetUsage();
                $n = 0; $fresh = 0;
                foreach (self::listAll() as $it) {
                    if (!empty($it['builtin']) || !empty($it['usage'])) continue;
                    if (time() - (int)$it['mtime'] < 86400) { $fresh++; continue; }
                    Images::delete($it['path']);
                    $n++;
                }
                self::resetUsage();
                flash('success', 'Видалено невикористаних фото: ' . $n
                    . ($fresh ? '. Ще ' . $fresh . ' завантажені за останню добу — їх не чіпали, поки ви не прикріпили їх.' : '.'));
            }
            if ($action === 'delete') {
                $path = (string)($_POST['path'] ?? '');
                if (str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
                    $uses = self::usage($path);
                    if ($uses) {
                        $msg = 'Фото використовується (' . implode(', ', array_column($uses, 'label')) . ') — спочатку приберіть або замініть його там.';
                        if (($_POST['format'] ?? '') === 'json') json_response(['ok' => false, 'error' => $msg, 'usage' => $uses], 409);
                        flash('error', $msg);
                    } else {
                        Images::delete($path);
                        if (($_POST['format'] ?? '') === 'json') json_response(['ok' => true]);
                        flash('success', 'Фото видалено із сайту');
                    }
                } elseif (($_POST['format'] ?? '') === 'json') json_response(['ok' => false], 422);
            }
            redirect('/admin/media');
        }
        View::show('admin/media', [
            'items' => self::listAll(),
            'page_title' => 'Медіатека — Адмінпанель',
        ], 'layouts/admin');
    }
}
