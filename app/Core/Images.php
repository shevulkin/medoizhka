<?php
declare(strict_types=1);

/** Завантаження та адаптація зображень (GD) */
class Images
{
    public const MAX_SIDE = 2000;      // повний розмір
    public const QUALITY = 90;         // якість webp/jpg повного розміру
    public const THUMB_QUALITY = 86;   // якість превʼю й середнього розміру
    public const THUMB_SIDE = 480;     // превʼю
    public const MID_SIDE = 800;       // середній розмір для карток на екранах високої щільності

    /** Зберігає завантажене фото; повертає [шлях, ширина, висота, байти] або null */
    public static function saveUpload(array $file, string $prefix = 'img'): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 15 * 1024 * 1024) return null;
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;
        [$w, $h, $type] = $info;
        // Фото з телефона розпаковується в память як ширина×висота×4 байти (24 МП ≈ 100 МБ) і ще потрібні
        // зменшені копії. Піднімаємо ліміт ДО читання (якщо хостинг дозволяє) і відмовляємо, а не падаємо,
        // коли памʼяті все одно не вистачить: краще повідомлення «не вдалося», ніж біла сторінка на весь сайт.
        @ini_set('memory_limit', '512M');
        $need = (int)($w * $h * 5.5) + 24 * 1048576;
        $lim = self::memoryLimit();
        if ($lim > 0 && $need > $lim - memory_get_usage(true)) return null;
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            default => false,
        };
        if (!$src) return null;

        $dir = cfg('uploads_dir');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = $prefix . '-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

        // масштабування до MAX_SIDE
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);   // оригінал більше не потрібен

        $useWebp = function_exists('imagewebp');
        $ext = $useWebp ? 'webp' : 'jpg';
        $full = "$dir/$name.$ext";
        $useWebp ? imagewebp($dst, $full, self::QUALITY) : imagejpeg($dst, $full, self::QUALITY);

        // превʼю й середній розмір — із вже зменшеного, без повторного читання файлу
        self::writeScaled($dst, $nw, $nh, self::THUMB_SIDE, "$dir/$name-thumb.$ext", self::THUMB_QUALITY, $useWebp);
        self::writeScaled($dst, $nw, $nh, self::MID_SIDE, "$dir/$name-md.$ext", self::THUMB_QUALITY + 4, $useWebp);

        imagedestroy($dst);
        $bytes = filesize($full) ?: 0;
        return ["uploads/$name.$ext", $nw, $nh, $bytes];
    }

    /** Ліміт памʼяті PHP в байтах (0 — без обмеження) */
    private static function memoryLimit(): int
    {
        $v = trim((string)ini_get('memory_limit'));
        if ($v === '' || $v === '-1') return 0;
        $n = (int)$v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    /** Зменшена копія готового зображення (GD) у файл; не більша за $side по довшій стороні */
    private static function writeScaled(\GdImage $im, int $w, int $h, int $side, string $to, int $q, bool $webp): void
    {
        $sc = min(1, $side / max($w, $h));
        $tw = max(1, (int)round($w * $sc)); $th = max(1, (int)round($h * $sc));
        $t = imagecreatetruecolor($tw, $th);
        imagealphablending($t, false); imagesavealpha($t, true);
        imagecopyresampled($t, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        $webp ? imagewebp($t, $to, $q) : imagejpeg($t, $to, $q);
        imagedestroy($t);
    }
    public static function thumbPath(string $path): string
    {
        return preg_replace('/\.(webp|jpg|png)$/', '-thumb.$1', $path) ?? $path;
    }

    /** Шлях до маленького превью для сіток (каталог, галерея); якщо превью немає — повертає оригінал */
    /** Шлях середнього розміру (-md) або null, якщо файлу немає */
    public static function midPath(string $path): ?string
    {
        $mid = preg_replace('/\.(webp|jpg|png)$/', '-md.$1', $path);
        return ($mid && $mid !== $path && is_file(BOFU_ROOT . '/assets/' . $mid)) ? $mid : null;
    }

    /** Створює «-md» (до 800 px, якість 88) із повного файлу; повертає true, якщо створено */
    public static function makeMid(string $fullAbs): bool
    {
        $mid = preg_replace('/\.(webp|jpg|png)$/', '-md.$1', $fullAbs);
        if (!$mid || $mid === $fullAbs || !function_exists('imagecreatefromwebp')) return false;
        $src = match (strtolower(pathinfo($fullAbs, PATHINFO_EXTENSION))) {
            'webp' => @imagecreatefromwebp($fullAbs), 'jpg' => @imagecreatefromjpeg($fullAbs), 'png' => @imagecreatefrompng($fullAbs), default => false,
        };
        if (!$src) return false;
        $w = imagesx($src); $h = imagesy($src);
        $sc = min(1, self::MID_SIDE / max($w, $h));
        $nw = (int)round($w * $sc); $nh = (int)round($h * $sc);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = str_ends_with($mid, '.webp') ? imagewebp($dst, $mid, self::THUMB_QUALITY + 4) : (str_ends_with($mid, '.png') ? imagepng($dst, $mid) : imagejpeg($dst, $mid, self::THUMB_QUALITY + 4));
        imagedestroy($src); imagedestroy($dst);
        return (bool)$ok;
    }

    /** srcset для картки: 480 w і 800 w (якщо є середній розмір) */
    public static function cardSrcset(string $path): string
    {
        $thumb = self::displayThumb($path);
        $mid = self::midPath($path);
        if ($thumb === $path || !$mid) return '';
        $tw = (int)(@getimagesize(BOFU_ROOT . '/assets/' . $thumb)[0] ?? 0);
        $mw = (int)(@getimagesize(BOFU_ROOT . '/assets/' . $mid)[0] ?? 0);
        if ($tw <= 0 || $mw <= $tw) return '';
        return asset($thumb) . " {$tw}w, " . asset($mid) . " {$mw}w";
    }

    public static function displayThumb(string $path): string
    {
        $thumb = self::thumbPath($path);
        return is_file(BOFU_ROOT . '/assets/' . $thumb) ? $thumb : $path;
    }

    public static function delete(string $path): void
    {
        $abs = BOFU_ROOT . '/assets/' . $path;
        @unlink($abs);
        @unlink(BOFU_ROOT . '/assets/' . self::thumbPath($path));
        if ($m = self::midPath($path)) @unlink(BOFU_ROOT . '/assets/' . $m);
    }
}
