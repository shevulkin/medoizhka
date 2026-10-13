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
 *  • відвідувач нічого не чекає: запит лише «стукає» в окремий внутрішній адрес сайту (/_sched/…) і одразу
 *    відпускає зʼєднання; самі завдання йдуть в ОКРЕМОМУ процесі, а не в запиті відвідувача. Якщо такий виклик
 *    не вдався, завдання виконуються після відправки відповіді (fastcgi_finish_request / litespeed_finish_request);
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
        if (preg_match('~^/(pay|auth|edit|admin/media|_sched|sw\.js|manifest|feeds|sitemap|robots)~', $p)) return;
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
            if ($due) { $state['_run'] = $due; foreach ($due as $n) $state[$n] = $now; }
            self::save($fh, $state);
            if (!$due) return;

            // Основний шлях: окремий процес. Запит відвідувача тільки стукає й одразу йде далі.
            if (self::dispatch()) return;

            // Запасний шлях: виконуємо тут, але вже після відправки відповіді
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
            @ignore_user_abort(true);
            @set_time_limit(25);

            self::runList($due);
            $state['_run'] = [];
            self::save($fh, $state);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** Секретний фрагмент адреси: без нього /_sched/… ніхто, крім самого сайту, не викличе */
    private static function token(): string
    {
        $db = (array)cfg('db');
        return substr(hash_hmac('sha256', 'scheduler', (string)($db['password'] ?? '') . (string)($db['database'] ?? '') . BOFU_ROOT), 0, 32);
    }

    /**
     * «Постукати» у внутрішній адрес сайту й одразу закрити зʼєднання, не чекаючи відповіді.
     * Повертає false, якщо не вдалось (тоді завдання виконаються після відповіді, як запасний шлях).
     */
    private static function dispatch(): bool
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || !preg_match('~^[A-Za-z0-9.:-]+$~', $host)) return false;
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $hostOnly = preg_replace('~:\d+$~', '', $host);
        $port = $https ? 443 : (int)(preg_match('~:(\d+)$~', $host, $m) ? $m[1] : 80);
        $fp = @stream_socket_client(($https ? 'ssl://' : 'tcp://') . $hostOnly . ':' . $port, $errno, $errstr, 1.5, STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
        if (!$fp) return false;
        $path = base_url('/_sched/' . self::token());
        $ok = @fwrite($fp, "GET $path HTTP/1.1\r\nHost: $host\r\nUser-Agent: medoizhka-scheduler\r\nConnection: close\r\n\r\n") !== false;
        @fclose($fp);   // не читаємо відповідь: сервер доробить сам (ignore_user_abort)
        return $ok;
    }

    /** Обробник /_sched/{token}: виконує відмічені завдання в окремому процесі */
    public static function handle(string $token): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        if (!hash_equals(self::token(), $token)) { http_response_code(404); exit('not found'); }
        @ignore_user_abort(true);
        @set_time_limit(120);
        $file = self::stateFile();
        $fh = @fopen($file, 'c+');
        if (!$fh) exit('no state');
        // Замок: два «стуки» одночасно не виконають одне й те саме двічі
        // (запит, що «стукав», ще міг тримати замок кілька мілісекунд — чекаємо до 3 с)
        $locked = false;
        for ($i = 0; $i < 15 && !($locked = flock($fh, LOCK_EX | LOCK_NB)); $i++) usleep(200000);
        if (!$locked) { fclose($fh); exit('busy'); }
        $state = json_decode((string)stream_get_contents($fh), true);
        $list = is_array($state) ? array_values(array_intersect((array)($state['_run'] ?? []), array_keys(self::TASKS))) : [];
        if ($list) { self::runList($list); $state['_run'] = []; self::save($fh, $state); }
        flock($fh, LOCK_UN); fclose($fh);
        exit('ok ' . implode(',', $list));
    }

    private static function runList(array $names): void
    {
        foreach ($names as $name) {
            try { self::run($name); }
            catch (\Throwable $e) {
                @file_put_contents(BOFU_ROOT . '/storage/logs/app-error.log',
                    '[' . date('Y-m-d H:i:s') . '] Scheduler ' . $name . ': ' . $e->getMessage() . "\n", FILE_APPEND);
            }
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
