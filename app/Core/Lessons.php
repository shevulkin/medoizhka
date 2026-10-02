<?php
declare(strict_types=1);

/**
 * Уроки курсу: відео в Bunny Stream, прогрес, «вивчено» й особисті нотатки.
 *
 * Курс лишається товаром (Courses), а урок — це одне відео в бібліотеці Bunny.
 * Доступ до уроків — це доступ до курсу (Courses::isOpen), а не окреме право.
 *
 * Що запамʼятовується для кожної людини окремо:
 *  - позиція перегляду — щоб повернутись до відео з того місця, де зупинилась;
 *  - позначка «вивчено» — щоб бачити, що вже пройдено;
 *  - нотатка — особиста, нікому, крім автора, не видна.
 * Усе це лежить в одному рядку на пару (людина, урок) і не залежить від пристрою:
 * почала на компʼютері — продовжила на телефоні.
 */
class Lessons
{
    /** Скільки секунд дійсне підписане посилання на плеєр. Відео триває довго, тому з запасом. */
    private const TOKEN_TTL = 6 * 3600;

    public static function forCourse(int $productId): array
    {
        return DB::all('SELECT * FROM course_lessons WHERE product_id = ? ORDER BY sort, id', [$productId]);
    }

    public static function find(int $lessonId): ?array
    {
        return DB::row('SELECT * FROM course_lessons WHERE id = ?', [$lessonId]);
    }

    /**
     * Чи може ця людина дивитись цей урок.
     * Персонал — завжди (перевірити матеріали), решта — лише з відкритим доступом до курсу
     * або якщо урок позначений безкоштовним оглядом.
     */
    public static function canWatch(?int $userId, array $lesson): bool
    {
        if (!empty($lesson['free_preview'])) return true;
        if (!$userId) return false;
        if (Auth::isStaff()) return true;
        return Courses::isOpen($userId, (int)$lesson['product_id']);
    }

    public static function libraryId(): string
    {
        return (string)(cfg('bunny.library') ?: Settings::get('bunny_library_id', '573243'));
    }

    private static function tokenKey(): string
    {
        return (string)(cfg('bunny.token_key') ?: Settings::get('bunny_token_key', ''));
    }

    public static function configured(): bool { return self::tokenKey() !== ''; }

    /**
     * API-ключ бібліотеки Bunny Stream (бібліотека → API → API Key). Це НЕ ключ
     * підписаних посилань: з ним адмінпанель бачить список відео й обирає їх
     * галочками, а не вимагає копіювати guid по одному.
     */
    public static function apiKey(): string
    {
        return trim((string)(cfg('bunny.api_key') ?: Settings::get('bunny_api_key', '')));
    }

    /** Запит до Bunny Stream API; null — ключа немає або Bunny не відповів */
    private static function api(string $path): ?array
    {
        $key = self::apiKey();
        if ($key === '' || !function_exists('curl_init')) return null;
        $ch = curl_init('https://video.bunnycdn.com/library/' . rawurlencode(self::libraryId()) . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['AccessKey: ' . $key, 'Accept: application/json']]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !is_string($body)) return null;
        $j = json_decode($body, true);
        return is_array($j) ? $j : null;
    }

    /**
     * Усі відео бібліотеки, новіші зверху: [['guid','title','length'(сек),'date'], …].
     * null — ключа немає або Bunny не відповів (тоді лишається вставка посилань).
     */
    public static function libraryVideos(): ?array
    {
        $out = [];
        for ($page = 1; $page <= 20; $page++) {
            $j = self::api('/videos?page=' . $page . '&itemsPerPage=100&orderBy=date');
            if ($j === null) return $page === 1 ? null : $out;
            foreach ($j['items'] ?? [] as $v) {
                $out[] = ['guid' => (string)$v['guid'], 'title' => trim((string)($v['title'] ?? '')),
                          'length' => (int)($v['length'] ?? 0), 'date' => (string)($v['dateUploaded'] ?? '')];
            }
            if (count($j['items'] ?? []) < 100) break;
        }
        return $out;
    }

    /** Назва й тривалість одного відео з Bunny — щоб не вписувати їх руками */
    public static function videoInfo(string $guid): ?array
    {
        $j = self::api('/videos/' . rawurlencode($guid));
        return $j ? ['title' => trim((string)($j['title'] ?? '')), 'length' => (int)($j['length'] ?? 0)] : null;
    }

    /**
     * Відео з того, що вставили: посилання на плеєр, на сторінку відео в кабінеті Bunny
     * чи сам guid — по одному в рядку; назва — після «|» або просто поруч із посиланням.
     * @return array<array{guid:string,title:string}>
     */
    public static function parseVideoLines(string $text): array
    {
        $out = [];
        foreach (preg_split('~\R+~', $text) as $line) {
            if (!preg_match('~[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}~i', $line, $m)) continue;
            $guid = strtolower($m[0]);
            $title = str_contains($line, '|') ? trim(explode('|', $line, 2)[1]) : '';
            if ($title === '') {   // «Вступ https://iframe.mediadelivery.net/play/…» — назва поруч із посиланням
                $title = trim(preg_replace(['~https?://\S+~', '~[0-9a-f-]{36}~i', '~\s+~u'], ['', '', ' '], $line), " \t-–—:|");
            }
            // те саме відео двічі — лишається перше, але назва з будь-якого рядка, де вона є
            if (!isset($out[$guid]) || ($out[$guid]['title'] === '' && $title !== '')) $out[$guid] = ['guid' => $guid, 'title' => $title];
        }
        return array_values($out);
    }

    /**
     * Адреса плеєра з підписом.
     *
     * Bunny Token Authentication для embed: token = SHA256_HEX(ключ + guid + expires).
     * Посилання живе кілька годин і прив'язане до відео, тому переслане комусь воно
     * швидко перестає працювати. Без ключа віддаємо адресу без підпису — це працює,
     * лише поки в бібліотеці не ввімкнено захист токеном; в адмінці про це є попередження.
     */
    public static function embedUrl(string $guid, bool $autoplay = false): string
    {
        $url = 'https://iframe.mediadelivery.net/embed/' . rawurlencode(self::libraryId()) . '/' . rawurlencode($guid)
            . '?autoplay=' . ($autoplay ? 'true' : 'false') . '&preload=true&responsive=true';
        $key = self::tokenKey();
        if ($key !== '') {
            $expires = time() + self::TOKEN_TTL;
            $url .= '&token=' . hash('sha256', $key . $guid . $expires) . '&expires=' . $expires;
        }
        return $url;
    }

    /** Прогрес людини по курсу: [lesson_id => ['position'=>int,'watched'=>bool]] */
    public static function progressMap(int $userId, int $productId): array
    {
        $rows = DB::all(
            'SELECT p.lesson_id, p.position, p.watched FROM lesson_progress p
               JOIN course_lessons l ON l.id = p.lesson_id
              WHERE p.user_id = ? AND l.product_id = ?', [$userId, $productId]);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['lesson_id']] = ['position' => (int)$r['position'], 'watched' => (bool)$r['watched']];
        return $out;
    }

    public static function notesMap(int $userId, int $productId): array
    {
        $rows = DB::all(
            'SELECT n.lesson_id, n.note FROM lesson_notes n
               JOIN course_lessons l ON l.id = n.lesson_id
              WHERE n.user_id = ? AND l.product_id = ?', [$userId, $productId]);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['lesson_id']] = (string)$r['note'];
        return $out;
    }

    /** Скільки уроків вивчено з усіх: [done, total] */
    public static function summary(int $userId, int $productId): array
    {
        $total = (int)DB::val('SELECT COUNT(*) FROM course_lessons WHERE product_id = ?', [$productId]);
        $done = (int)DB::val(
            'SELECT COUNT(*) FROM lesson_progress p JOIN course_lessons l ON l.id = p.lesson_id
              WHERE p.user_id = ? AND l.product_id = ? AND p.watched = 1', [$userId, $productId]);
        return [$done, $total];
    }

    /** Урок, з якого варто продовжити: останній, що дивилась, а якщо нічого — перший невивчений */
    public static function resumeLesson(int $userId, array $lessons): ?array
    {
        if (!$lessons) return null;
        $ids = array_map(fn($l) => (int)$l['id'], $lessons);
        $in = implode(',', $ids);
        $last = DB::row("SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND lesson_id IN ($in)
                          ORDER BY updated_at DESC, id DESC LIMIT 1", [$userId]);
        if ($last) {
            foreach ($lessons as $l) if ((int)$l['id'] === (int)$last['lesson_id']) return $l;
        }
        $map = DB::all("SELECT lesson_id FROM lesson_progress WHERE user_id = ? AND watched = 1 AND lesson_id IN ($in)", [$userId]);
        $done = array_flip(array_map(fn($r) => (int)$r['lesson_id'], $map));
        foreach ($lessons as $l) if (!isset($done[(int)$l['id']])) return $l;
        return $lessons[0];
    }

    public static function savePosition(int $userId, int $lessonId, int $seconds): void
    {
        $seconds = max(0, min($seconds, 86400));
        $row = DB::row('SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]);
        if ($row) DB::update('lesson_progress', ['position' => $seconds, 'updated_at' => now()], 'id = ?', [$row['id']]);
        else DB::insert('lesson_progress', ['user_id' => $userId, 'lesson_id' => $lessonId, 'position' => $seconds, 'updated_at' => now()]);
    }

    /** Вивчено / не вивчено. Повертає новий стан. */
    public static function setWatched(int $userId, int $lessonId, ?bool $state = null): bool
    {
        $row = DB::row('SELECT id, watched FROM lesson_progress WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]);
        $new = $state ?? !($row && $row['watched']);
        $data = ['watched' => $new ? 1 : 0, 'watched_at' => $new ? now() : null, 'updated_at' => now()];
        if ($row) DB::update('lesson_progress', $data, 'id = ?', [$row['id']]);
        else DB::insert('lesson_progress', $data + ['user_id' => $userId, 'lesson_id' => $lessonId, 'position' => 0]);
        return $new;
    }

    public static function saveNote(int $userId, int $lessonId, string $note): void
    {
        $note = mb_substr($note, 0, 20000);
        $row = DB::row('SELECT id FROM lesson_notes WHERE user_id = ? AND lesson_id = ?', [$userId, $lessonId]);
        if ($row) DB::update('lesson_notes', ['note' => $note, 'updated_at' => now()], 'id = ?', [$row['id']]);
        else DB::insert('lesson_notes', ['user_id' => $userId, 'lesson_id' => $lessonId, 'note' => $note, 'updated_at' => now()]);
    }

    public static function fmt(int $sec): string
    {
        $h = intdiv($sec, 3600); $m = intdiv($sec % 3600, 60); $s = $sec % 60;
        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}
