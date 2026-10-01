<?php
declare(strict_types=1);
/**
 * Аудит перенесення: кожна адреса зі sitemap старого сайту має відкриватися на новому
 * тією самою адресою, з тим самим title, description і canonical (шляхом).
 *
 *   php bin/seo-audit.php [http://localhost/medoizhka]
 *
 * Звіт: storage/logs/seo-audit.txt. Код виходу 1, якщо є розбіжності.
 */
$new = rtrim($argv[1] ?? 'http://localhost/medoizhka', '/');
$old = 'https://medoizhka.com';

function get(string $url, ?int &$code = null, ?string &$loc = null): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 0, CURLOPT_TIMEOUT => 40,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'medoizhka-seo-audit', CURLOPT_HEADER => 1]);
    $res = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $loc = preg_match('~^Location:\s*(\S+)~mi', substr($res, 0, $hs), $m) ? $m[1] : null;
    curl_close($ch);
    return substr($res, $hs);
}
function meta(string $html): array
{
    $t = preg_match('~<title>(.*?)</title>~si', $html, $m) ? html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    $d = preg_match('~<meta name="description" content="([^"]*)"~i', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
    $c = preg_match('~<link rel="canonical" href="([^"]*)"~i', $html, $m) ? $m[1] : '';
    $path = $c !== '' ? (parse_url($c, PHP_URL_PATH) ?? '') : '';
    return ['title' => $t, 'desc' => trim($d), 'canon' => $path];
}
$norm = fn(string $s) => mb_strtolower(preg_replace('~\s+~u', ' ', str_replace(['–', '—', '’', 'ʼ', '…'], ['-', '-', "'", "'", ''], trim($s))));

$urls = [];
if (!is_file(__DIR__ . '/../migration/seo-snapshot.json')) foreach (['page', 'product', 'product_cat', 'product_tag', 'product_brand', 'category', 'author', 'post'] as $s) {
    $xml = get("$old/$s-sitemap.xml");
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
    foreach ($m[1] as $u) $urls[] = $u;
}
$urls = array_values(array_unique($urls));
// Знімок старого сайту: після перемикання домену його вже не буде, тож порівнюємо зі збереженим
$snapFile = __DIR__ . '/../migration/seo-snapshot.json';
$snap = is_file($snapFile) ? (json_decode(file_get_contents($snapFile), true) ?: []) : [];
if ($snap) $urls = array_keys($snap);
$base = parse_url($new, PHP_URL_PATH) ?: '';
$out = []; $bad = 0;
foreach ($urls as $u) {
    $path = substr($u, strlen($old));
    $o = $snap[$u] ?? ($snap[$u] = meta(get($u)));
    $html = get($new . $path, $code, $loc);
    if ($code === 301 && $loc) { $out[] = "301   $path → " . substr($loc, strlen($new)); continue; }
    $n = meta($html);
    $issues = [];
    if ($code !== 200) $issues[] = "HTTP $code";
    if ($code === 200) {
        if ($norm($o['title']) !== $norm($n['title'])) $issues[] = "title: «{$n['title']}» ≠ «{$o['title']}»";
        if ($n['desc'] === '') $issues[] = 'description порожній';
        elseif ($o['desc'] !== '' && !str_starts_with($norm($o['desc']), rtrim($norm($n['desc']), ' .')) && !str_starts_with($norm($n['desc']), rtrim($norm($o['desc']), ' .'))) $issues[] = 'description відрізняється';
        $nc = $base !== '' && str_starts_with($n['canon'], $base) ? substr($n['canon'], strlen($base)) : $n['canon'];
        if (mb_strtolower(rawurldecode($nc)) !== mb_strtolower(rawurldecode($o['canon'] ?: $path))) $issues[] = "canonical $nc ≠ {$o['canon']}";
    }
    if ($issues) { $bad++; $out[] = "FAIL  $path\n      " . implode("\n      ", $issues); }
    else $out[] = "OK    $path";
}
if (!is_file($snapFile)) file_put_contents($snapFile, json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$report = implode("\n", $out) . "\n\nВсього: " . count($urls) . ", з розбіжностями: $bad\n";
@mkdir(__DIR__ . '/../storage/logs', 0775, true);
file_put_contents(__DIR__ . '/../storage/logs/seo-audit.txt', $report);
echo $report;
exit($bad ? 1 : 0);
