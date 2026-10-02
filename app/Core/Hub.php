<?php
declare(strict_types=1);

/**
 * Дані каталогів «Апітерапевти» та «Пасіки й апібудиночки»: вибірки й заявки.
 *
 * Гроші за візит чи сеанс сайт не приймає: заявка — це знайомство. Далі домовляються
 * власник місця чи фахівець і гість напряму. Тому тут немає ні кошика, ні оплати,
 * а є лише заявка, яка одразу летить адміністраторам через Notify (Telegram, пошта, push).
 */
class Hub
{
    public const PLACE_KINDS = [
        'apiary' => ['Відвідування пасіки', 'Пасіки'],
        'apihouse' => ['Апібудиночок', 'Апібудиночки'],
        'workshop' => ['Майстер-клас', 'Майстер-класи'],
    ];

    public static function practitioners(array $f = []): array
    {
        $w = ['active = 1']; $a = [];
        if (!empty($f['region'])) { $w[] = 'region = ?'; $a[] = $f['region']; }
        if (!empty($f['online'])) { $w[] = 'online = 1'; }
        if (!empty($f['q'])) { $w[] = '(name LIKE ? OR specialties LIKE ? OR city LIKE ?)'; $like = '%' . $f['q'] . '%'; array_push($a, $like, $like, $like); }
        $limit = isset($f['limit']) ? ' LIMIT ' . (int)$f['limit'] : '';
        return DB::all('SELECT * FROM practitioners WHERE ' . implode(' AND ', $w) . ' ORDER BY verified DESC, sort, name' . $limit, $a);
    }

    /** Чи є хоч одне місце для відвідування — без цього розділ «Пасіки» не показуємо */
    public static function hasPlaces(): bool
    {
        static $has = null;
        return $has ??= (bool)DB::val('SELECT 1 FROM places WHERE active = 1 LIMIT 1');
    }

    public static function places(array $f = []): array
    {
        $w = ['active = 1']; $a = [];
        if (!empty($f['kind']) && isset(self::PLACE_KINDS[$f['kind']])) { $w[] = 'kind = ?'; $a[] = $f['kind']; }
        if (!empty($f['region'])) { $w[] = 'region = ?'; $a[] = $f['region']; }
        $limit = isset($f['limit']) ? ' LIMIT ' . (int)$f['limit'] : '';
        return DB::all('SELECT * FROM places WHERE ' . implode(' AND ', $w) . ' ORDER BY sort, name' . $limit, $a);
    }

    public static function regions(string $table): array
    {
        $t = $table === 'places' ? 'places' : 'practitioners';
        return array_column(DB::all("SELECT DISTINCT region FROM $t WHERE active = 1 AND region IS NOT NULL AND region <> '' ORDER BY region"), 'region');
    }

    /** Список з рядка «по пункту в рядку або через кому» */
    public static function lines(?string $text): array
    {
        if ($text === null || trim($text) === '') return [];
        $parts = preg_split('~[\r\n]+|\s*,\s*~u', trim($text));
        return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
    }

    public static function kindLabel(string $kind, bool $plural = false): string
    {
        return self::PLACE_KINDS[$kind][$plural ? 1 : 0] ?? $kind;
    }

    /** Зберегти заявку й сповістити. Повертає id або null, якщо дані не пройшли перевірку. */
    public static function saveBooking(string $kind, array $subject, array $in): ?int
    {
        $name = trim((string)($in['name'] ?? ''));
        $phone = trim((string)($in['phone'] ?? ''));
        if (mb_strlen($name) < 2 || preg_match_all('~\d~', $phone) < 9) return null;
        $date = trim((string)($in['on_date'] ?? ''));
        if ($date !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) $date = '';
        $guests = max(0, min(99, (int)($in['guests'] ?? 0))) ?: null;
        $msg = mb_substr(trim((string)($in['message'] ?? '')), 0, 2000);
        $email = trim((string)($in['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

        $id = DB::insert('bookings', [
            'kind' => $kind, 'ref_id' => (int)$subject['id'], 'user_id' => Auth::id(),
            'name' => mb_substr($name, 0, 120), 'phone' => mb_substr($phone, 0, 40),
            'email' => $email ?: null, 'on_date' => $date ?: null, 'guests' => $guests,
            'message' => $msg ?: null, 'status' => 'new', 'created_at' => now(),
        ]);
        Notify::fire('booking_new', [
            'what' => $kind === 'place' ? 'Заявка на візит (' . mb_strtolower(self::kindLabel($subject['kind'])) . ')' : 'Заявка до апітерапевта',
            'title' => $subject['name'], 'date' => $date ?: 'не вказано', 'guests' => $guests ?: '—',
            'name' => $name, 'phone' => $phone, 'note' => $msg,
        ]);
        return $id;
    }
}
