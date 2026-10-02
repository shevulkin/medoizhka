<?php
declare(strict_types=1);
/**
 * Наявність, ціни й фасовки зі старої бази WordPress — у нову. Стару базу ЛИШЕ ЧИТАЄ.
 *
 *   php bin/wp-stock.php                 — показати, що зміниться (нічого не записує)
 *   php bin/wp-stock.php --apply         — записати
 *   php bin/wp-stock.php --apply [шлях до старого wp-config.php]
 *   Без шляху стару установку шукає сам (див. bin/wp-old.php).
 *
 * Навіщо. Перший імпорт брав товари зі сторінок сайту й бачив лише те, що WordPress
 * показував покупцю. Наявності там не видно, тож усе отримало «є, 50 шт», а товари,
 * у яких закінчились усі фасовки, — ще й «ціну уточнюйте» (WooCommerce ховає ціну
 * фасовок, яких немає). Стара база знає правду: статус запасу кожного товару й
 * кожної фасовки, справжню кількість (якщо її вели), ціни й дозвіл на передзамовлення.
 *
 * Що робить:
 *  - залишок: «є» → кількість зі старої бази (якщо там вели облік) або 50; «немає» → 0;
 *  - «під замовлення» вмикає лише там, де WordPress дозволяв передзамовлення (backorders);
 *  - дописує ціну й фасовки, яких бракує; наявні ціни не чіпає — їх могли вже виправити в адмінці.
 * Повторний запуск безпечний.
 */
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), fn($a) => $a !== '--apply'));
require __DIR__ . '/wp-old.php';
[$wp, $prefix] = wpOldConnect($args[0] ?? null);
$q = function (string $sql, array $a = []) use ($wp): array { $s = $wp->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); };
$v = fn(string $sql, array $a = []) => ($r = $q($sql, $a)) ? array_values($r[0])[0] : null;
$T = fn(string $name) => $prefix . $name;

$meta = function (int $id) use ($q, $T): array {
    $out = [];
    foreach ($q("SELECT meta_key, meta_value FROM {$T('postmeta')} WHERE post_id = ?", [$id]) as $r) $out[$r['meta_key']] = $r['meta_value'];
    return $out;
};
$typeOf = fn(int $id) => (string)($v("SELECT t.slug FROM {$T('term_relationships')} tr
    JOIN {$T('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
    JOIN {$T('terms')} t ON t.term_id = tt.term_id WHERE tr.object_id = ?", [$id]) ?: 'simple');
$termName = fn(string $tax, string $slug) => (string)($v("SELECT t.name FROM {$T('terms')} t
    JOIN {$T('term_taxonomy')} tt ON tt.term_id = t.term_id AND tt.taxonomy = ? WHERE t.slug = ?", [$tax, $slug]) ?: urldecode($slug));

$num = fn($x) => ($x === null || $x === '' || (float)$x <= 0) ? null : (float)$x;
/** [ціна зараз, стара ціна] — акційна ціна, якщо вона є, інакше звичайна */
$priceOf = function (array $m) use ($num): array {
    $reg = $num($m['_regular_price'] ?? null); $sale = $num($m['_sale_price'] ?? null);
    if ($sale !== null && $reg !== null && $sale < $reg) return [$sale, $reg];
    return [$reg ?? $num($m['_price'] ?? null), null];
};
/** Кількість на складі за статусом WordPress */
$qtyOf = function (array $m, int $current): int {
    if (($m['_stock_status'] ?? 'instock') !== 'instock') return 0;
    if (($m['_manage_stock'] ?? 'no') === 'yes') return max(0, (int)floor((float)($m['_stock'] ?? 0)));
    return $current > 0 ? $current : 50;
};
$norm = fn(string $s) => mb_strtolower(preg_replace('~\s+~u', ' ', trim(str_replace(['’', 'ʼ'], "'", $s))));

$store = (int)DB::val('SELECT id FROM stores WHERE active = 1 ORDER BY sort, id LIMIT 1');
if (!$store) { fwrite(STDERR, "У новій базі немає активного магазину\n"); exit(1); }
$curQty = fn(int $pid, ?int $vid) => (int)DB::val('SELECT COALESCE(SUM(qty),0) FROM store_stock WHERE product_id = ? AND store_id = ? AND '
    . ($vid === null ? 'variant_id IS NULL' : 'variant_id = ?'), $vid === null ? [$pid, $store] : [$pid, $store, $vid]);

/*
 * Послуги складу не мають: «Купимо віск» чи обмін воску не «закінчуються». У WordPress їх
 * позначали «немає», щоб сховати кнопку кошика, — переносити це як «немає в наявності»
 * означало б написати на вітрині нісенітницю. Їх наявність не чіпаємо.
 */
const SERVICE_CATS = ['services', 'wax-exchange'];

$stat = ['in' => [], 'order' => [], 'out' => [], 'prices' => 0, 'variants' => 0, 'missing' => [], 'services' => []];

// Курси не беремо: доступ до відео від складу не залежить (Catalog::AVAIL_SQL)
foreach (DB::all("SELECT p.*, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id
                  WHERE p.wp_id IS NOT NULL AND p.type <> 'course' ORDER BY p.id") as $p) {
    $pid = (int)$p['id']; $wid = (int)$p['wp_id'];
    if (!empty($p['service']) || in_array($p['cat_slug'], SERVICE_CATS, true)) { $stat['services'][] = $p['name']; continue; }
    if (!$v("SELECT ID FROM {$T('posts')} WHERE ID = ? AND post_type = 'product'", [$wid])) { $stat['missing'][] = $p['name']; continue; }
    $m = $meta($wid);
    $stock = [];            // [variant_id|0 => qty]
    $mto = ($m['_backorders'] ?? 'no') !== 'no';
    $setPrice = null; $setOld = null;

    if ($typeOf($wid) === 'variable') {
        $ours = DB::all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort, id', [$pid]);
        $used = []; $prices = []; $sort = count($ours);
        // Вимкнені фасовки WooCommerce зберігає як private — їх не продавали, не переносимо
        $children = $q("SELECT ID FROM {$T('posts')} WHERE post_parent = ? AND post_type = 'product_variation' AND post_status = 'publish'
                        ORDER BY menu_order, ID", [$wid]);
        foreach ($children as $i => $c) {
            $cm = $meta((int)$c['ID']);
            $labels = [];
            foreach ($cm as $k => $val) {
                if (!str_starts_with($k, 'attribute_') || $val === '') continue;
                $tax = substr($k, 10);
                $labels[] = str_starts_with($tax, 'pa_') ? $termName($tax, $val) : $val;
            }
            $name = implode(' / ', $labels) ?: ('Варіант ' . ($i + 1));
            $sku = trim((string)($cm['_sku'] ?? ''));
            // Зіставлення: за артикулом, далі за назвою фасовки
            $match = null;
            foreach ($ours as $o) {
                if (isset($used[$o['id']])) continue;
                if (($sku !== '' && $sku === (string)$o['sku']) || $norm($name) === $norm((string)$o['name'])) { $match = $o; break; }
            }
            [$vp] = $priceOf($cm);
            if ($vp !== null) $prices[] = $vp;
            if ($match) {
                $vid = (int)$match['id']; $used[$vid] = true;
                if ($match['price'] === null && $vp !== null) {
                    $stat['prices']++;
                    if ($apply) DB::update('product_variants', ['price' => $vp], 'id = ?', [$vid]);
                }
            } else {
                $stat['variants']++;
                $vid = $apply ? (int)DB::insert('product_variants', ['product_id' => $pid, 'name' => $name, 'price' => $vp,
                    'sku' => $sku ?: null, 'sort' => $sort++, 'active' => 1]) : -($stat['variants']);
            }
            $stock[$vid] = $qtyOf($cm, $vid > 0 ? $curQty($pid, $vid) : 0);
            if (($cm['_backorders'] ?? 'no') !== 'no') $mto = true;
        }
        if ($p['base_price'] === null && $prices) $setPrice = min($prices);
    } else {
        [$pr, $old] = $priceOf($m);
        if ($p['base_price'] === null && $pr !== null) { $setPrice = $pr; $setOld = $old; }
        $stock[0] = $qtyOf($m, $curQty($pid, null));
    }

    $total = array_sum($stock);
    $bucket = $total > 0 ? 'in' : ($mto ? 'order' : 'out');
    $stat[$bucket][] = $p['name'];
    if ($setPrice !== null) $stat['prices']++;

    if ($apply) {
        $upd = ['made_to_order' => $mto ? 1 : 0, 'updated_at' => now()];
        if ($setPrice !== null) { $upd['base_price'] = $setPrice; if ($setOld !== null) $upd['old_price'] = $setOld; }
        DB::update('products', $upd, 'id = ?', [$pid]);
        // Залишки цієї точки пишемо наново: заодно зникають рядки фасовок, яких уже немає
        DB::delete('store_stock', 'product_id = ? AND store_id = ?', [$pid, $store]);
        foreach ($stock as $vid => $qty) {
            DB::insert('store_stock', ['product_id' => $pid, 'variant_id' => $vid ?: null, 'store_id' => $store, 'qty' => $qty]);
        }
    }
}

echo 'В наявності: ', count($stat['in']), "\n";
if ($stat['order']) echo 'Під замовлення (у WordPress було дозволено передзамовлення): ', count($stat['order']), "\n  - ", implode("\n  - ", $stat['order']), "\n";
echo 'Немає в наявності: ', count($stat['out']), ($stat['out'] ? "\n  - " . implode("\n  - ", $stat['out']) : ''), "\n";
echo "Цін дописано: {$stat['prices']}, фасовок додано: {$stat['variants']}\n";
if ($stat['services']) echo 'Послуги — наявність не застосовується, лишились як є: ', implode(', ', $stat['services']), "\n";
if ($stat['missing']) echo 'У старій базі не знайшлось (лишились як є): ', implode(', ', $stat['missing']), "\n";
echo $apply ? "Записано.\n" : "\nЦе перегляд — нічого не записано. Щоб записати, додайте --apply\n";
