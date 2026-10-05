<?php
declare(strict_types=1);

/** Завантаження та адаптація зображень (GD) */
class Images
{
    public const MAX_SIDE = 1600;      // повний розмір
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

        $useWebp = function_exists('imagewebp');
        $ext = $useWebp ? 'webp' : 'jpg';
        $full = "$dir/$name.$ext";
        $useWebp ? imagewebp($dst, $full, 85) : imagejpeg($dst, $full, 85);

        // превʼю
        $tScale = min(1, self::THUMB_SIDE / max($nw, $nh));
        $tw = (int)round($nw * $tScale); $th = (int)round($nh * $tScale);
        $thumb = imagecreatetruecolor($tw, $th);
        imagealphablending($thumb, false); imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $dst, 0, 0, 0, 0, $tw, $th, $nw, $nh);
        $useWebp ? imagewebp($thumb, "$dir/$name-thumb.$ext", 82) : imagejpeg($thumb, "$dir/$name-thumb.$ext", 82);

        self::makeMid($full);

        imagedestroy($src); imagedestroy($dst); imagedestroy($thumb);
        $bytes = filesize($full) ?: 0;
        return ["uploads/$name.$ext", $nw, $nh, $bytes];
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
        $ok = str_ends_with($mid, '.webp') ? imagewebp($dst, $mid, 88) : (str_ends_with($mid, '.png') ? imagepng($dst, $mid) : imagejpeg($dst, $mid, 88));
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
