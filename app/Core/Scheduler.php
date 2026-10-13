<?php
declare(strict_types=1);

/**
 * Планувальник без cron.
 *
 * На деяких хостингах cron недоступний (у cPanel немає розділу «Cron Jobs»), а завдання, які раніше
 * ставились у cron (`php bin/cli.php np:track` тощо), потрібні: оновити статуси посилок, звірити оплати,
 * нагадати про торг, оновити відео YouTube. Тому їх запускає сам сайт — ПІСЛЯ того, як відповідь уже
 * відправлена відвідувачу, і не частіше, ніж вказано в TASKS.
 *
 * Що це гарантує:
 *  • відвідувач нічого не чекає: завдання йдуть після fastcgi_finish_request / litespeed_finish_request;
 *  • один запуск за раз: файл-замок (flock) — два запити одночасно не запустять те саме двічі;
 *  • нема підключень до бази понад звичайні: завдання користуються тим самим підключенням запиту;
 *  • збій завдання не ламає сайт: усе в try/catch, помилка йде в журнал;
 *  • нічого не робить, якщо завданню нічого робити (не заданий ключ НП, нема еквайрингу, нема каналу YouTube).
 *
 * Команди bin/cli.php лишаються робочими: можна запускати вручну або з cron, де він є.
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

    /** Не частіше за раз на хвилину перевіряємо, чи є що запускати */
    private const CHECK_EVERY = 60;

    private static function stateFile(): string { return BOFU_ROOT . '/storage/cache/scheduler.json'; }

    /** Реєструє запуск після відповіді. Кличеться на початку запиту. */
    public static function register(): void
    {
        // лише переглядам сторінок: не API оплати, не завантаження файлів, не службові запити
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        $p = request_path();
        if (preg_match('~^/(pay|auth|edit|admin/media|sw\.js|manifest|feeds|sitemap|robots)~', $p)) return;
        register_shutdown_function([self::class, 'runDue']);
    }

    public static function runDue(): void
    {
        $file = self::stateFile();
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;

        $fh = @fopen($file, 'c+');
        if (!$fh) return;
        // Не чекаємо замка: якщо хтось уже виконує, цей запит просто йде далі
        if (!flock($fh, LOCK_EX | LOCK_NB)) { fclose($fh); return; }

        try {
            $state = json_decode((string)stream_get_contents($fh), true);
            if (!is_array($state)) $state = [];
            $now = time();
            if ($now - (int)($state['_checked'] ?? 0) < self::CHECK_EVERY) return;
            $state['_checked'] = $now;

            $due = [];
            foreach (self::TASKS as $name => $every) {
                if ($now - (int)($state[$name] ?? 0) >= $every) $due[] = $name;
            }
            self::save($fh, $state);
            if (!$due) return;

            // відповідь уже в дорозі до відвідувача — далі він нас не чекає
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
            @ignore_user_abort(true);
            @set_time_limit(25);

            foreach ($due as $name) {
                $state[$name] = time();   // позначаємо до запуску: впало — наступна спроба за інтервалом, а не лавиною
                self::save($fh, $state);
                try { self::run($name); }
                catch (\Throwable $e) {
                    @file_put_contents(BOFU_ROOT . '/storage/logs/app-error.log',
                        '[' . date('Y-m-d H:i:s') . '] Scheduler ' . $name . ': ' . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
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
