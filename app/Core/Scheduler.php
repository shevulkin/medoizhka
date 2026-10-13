<?php
declare(strict_types=1);

/**
 * Фонові завдання за інтервалами. Запускає їх ЛИШЕ cron хостингу, одним рядком (кожні 5 хвилин):
 *
 *     php /home/USER/public_html/medoizhka/bin/cli.php schedule:run
 *
 * Відвідувачі сайту до цього не причетні: сторінки нічого не запускають і нічого не чекають.
 * Кожне завдання виконується не частіше свого інтервалу (TASKS); помилка завдання йде в журнал і не зупиняє решту.
 * Окремі команди (`np:track`, `pay:sync`, `offers:remind`, `yt:refresh` у bin/cli.php) лишаються для ручного запуску.
 */
class Scheduler
{
    /** завдання => інтервал у секундах */
    private const TASKS = [
        'np:track'      => 3600,   // статуси накладних Нової Пошти
        'pay:sync'      => 300,    // звірка незавершених оплат
        'offers:remind' => 3600,   // нагадування про торг
        'yt:refresh'    => 3600,   // відео YouTube
    ];

    private static function stateFile(): string { return BOFU_ROOT . '/storage/cache/scheduler.json'; }

    /** Для cron: виконує завдання, яким настав час. Повертає назви виконаних. */
    public static function runFromCron(): array
    {
        $file = self::stateFile();
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $fh = @fopen($file, 'c+');
        if (!$fh) return [];
        // Замок: якщо попередній запуск ще працює, цей чекає (cron раз на 5 хвилин, тож черги не виникне)
        if (!flock($fh, LOCK_EX)) { fclose($fh); return []; }
        try {
            $state = json_decode((string)stream_get_contents($fh), true);
            if (!is_array($state)) $state = [];
            $now = time();
            $state = ['_cron' => $now] + array_intersect_key($state, self::TASKS);   // _cron — відмітка «cron живий»
            $due = [];
            foreach (self::TASKS as $name => $every) {
                if ($now - (int)($state[$name] ?? 0) >= $every) $due[] = $name;
            }
            // позначаємо до запуску: впало — наступна спроба за інтервалом, а не кожні 5 хвилин
            foreach ($due as $n) $state[$n] = $now;
            self::save($fh, $state);
            foreach ($due as $name) {
                try { self::run($name); }
                catch (\Throwable $e) {
                    @file_put_contents(BOFU_ROOT . '/storage/logs/app-error.log',
                        '[' . date('Y-m-d H:i:s') . '] Scheduler ' . $name . ': ' . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
            return $due;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private static function save($fh, array $state): void
    {
        ftruncate($fh, 0); rewind($fh);
        fwrite($fh, json_encode($state));
        fflush($fh);
    }

    /** Те саме, що виконують однойменні команди bin/cli.php */
    public static function run(string $name): void
    {
        switch ($name) {
            case 'np:track':
                if (!NovaPoshta::enabled()) return;
                $due = Shipments::due(100);
                if ($due) Shipments::refresh($due);
                return;
            case 'pay:sync':
                if (Acquiring::missing()) return;
                foreach (Acquiring::due(50) as $p) Acquiring::sync($p);
                return;
            case 'offers:remind':
                Offers::maintain();
                return;
            case 'yt:refresh':
                if (trim((string)Settings::get('youtube_channel', '')) === '') return;
                YouTube::latest(100, true);
                return;
        }
    }
}
