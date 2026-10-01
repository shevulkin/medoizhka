<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Auth, Csrf, Courses, Lessons, Catalog;

/**
 * Навчання: «Мої курси», сторінка курсу з плеєром, прогрес, «вивчено», нотатки.
 *
 * Адреси збігаються зі старим сайтом: /my-account/my-course/ — перелік, а курс
 * відкривається на тій самій адресі, що й раніше (/volynec/, /dukarev/, …).
 * Людині без доступу ця ж адреса показує сторінку курсу з кнопкою купівлі.
 */
class Learn
{
    /** «Мої курси»: куплені курси з прогресом і кнопкою «Продовжити» */
    public static function my(): never
    {
        if (!Auth::check()) { flash('error', 'Увійдіть, щоб бачити свої курси.'); redirect('/'); }
        $uid = (int)Auth::id();
        $items = [];
        foreach (Courses::forUser($uid) as $c) {
            $pid = (int)$c['product']['id'];
            $lessons = Lessons::forCourse($pid);
            [$done, $total] = Lessons::summary($uid, $pid);
            $next = $c['expired'] ? null : Lessons::resumeLesson($uid, $lessons);
            $items[] = $c + ['done' => $done, 'total' => $total, 'next' => $next,
                             'started' => (bool)DB::val('SELECT 1 FROM lesson_progress p JOIN course_lessons l ON l.id = p.lesson_id WHERE p.user_id = ? AND l.product_id = ? LIMIT 1', [$uid, $pid])];
        }
        View::show('learn/my', [
            'items' => $items,
            'diplomas' => \Diplomas::forUser($uid),
            'page_title' => seo_title('Мої відеокурси'),
            'noindex' => true,
        ]);
    }

    /**
     * Сторінка курсу за кореневою адресою. Є доступ — плеєр, немає — вітрина курсу.
     */
    public static function course(string $slug): never
    {
        $p = Courses::bySlug($slug);
        if (!$p) { http_response_code(404); View::show('errors/404'); }
        $uid = Auth::id();
        $pid = (int)$p['id'];
        $lessons = Lessons::forCourse($pid);
        $hasPreview = (bool)array_filter($lessons, fn($l) => !empty($l['free_preview']));
        $open = $uid && (Auth::isStaff() || Courses::isOpen($uid, $pid));

        if (!$open && !$hasPreview) Home::course($slug);   // вітрина з кнопкою купівлі

        $progress = $uid ? Lessons::progressMap($uid, $pid) : [];
        $want = (int)($_GET['l'] ?? 0);
        $current = null;
        foreach ($lessons as $l) if ((int)$l['id'] === $want) $current = $l;
        $current ??= $uid ? Lessons::resumeLesson($uid, $lessons) : ($lessons[0] ?? null);
        [$done, $total] = $uid ? Lessons::summary($uid, $pid) : [0, count($lessons)];

        View::show('learn/player', [
            'prod' => $p, 'lessons' => $lessons, 'progress' => $progress, 'current' => $current,
            'open' => $open, 'done' => $done, 'total' => $total,
            'bunny_ok' => Lessons::configured(),
            'page_title' => seo_title($p['name']),
            'meta_description' => 'Навчальний курс «' . $p['name'] . '»: відеоуроки, нотатки й прогрес навчання.',
            'noindex' => true,
        ]);
    }

    /** Один урок: підписане посилання на плеєр, збережена позиція, стан і нотатка */
    public static function lesson(): never
    {
        $l = Lessons::find((int)($_GET['id'] ?? 0));
        if (!$l || !Lessons::canWatch(Auth::id(), $l)) json_response(['error' => 'Немає доступу до уроку.'], 403);
        $uid = Auth::id();
        $pos = 0; $watched = false; $note = '';
        if ($uid) {
            $pr = DB::row('SELECT position, watched FROM lesson_progress WHERE user_id = ? AND lesson_id = ?', [$uid, $l['id']]);
            $pos = (int)($pr['position'] ?? 0); $watched = (bool)($pr['watched'] ?? false);
            $note = (string)DB::val('SELECT note FROM lesson_notes WHERE user_id = ? AND lesson_id = ?', [$uid, $l['id']]);
        }
        json_response([
            'id' => (int)$l['id'], 'title' => $l['title'], 'description' => $l['description'],
            'embed' => Lessons::embedUrl($l['guid'], $pos <= 10),
            'position' => $pos, 'watched' => $watched, 'note' => $note,
        ]);
    }

    private static function ownLesson(): array
    {
        Csrf::verify();
        $uid = Auth::id();
        $l = Lessons::find((int)($_POST['lesson'] ?? 0));
        if (!$uid || !$l || !Lessons::canWatch($uid, $l)) json_response(['error' => 'Немає доступу.'], 403);
        return [$uid, $l];
    }

    public static function progress(): never
    {
        [$uid, $l] = self::ownLesson();
        Lessons::savePosition($uid, (int)$l['id'], (int)($_POST['position'] ?? 0));
        json_response(['ok' => true]);
    }

    public static function watched(): never
    {
        [$uid, $l] = self::ownLesson();
        $state = isset($_POST['state']) ? (bool)(int)$_POST['state'] : null;
        $new = Lessons::setWatched($uid, (int)$l['id'], $state);
        [$done, $total] = Lessons::summary($uid, (int)$l['product_id']);
        json_response(['ok' => true, 'watched' => $new, 'done' => $done, 'total' => $total]);
    }

    public static function note(): never
    {
        [$uid, $l] = self::ownLesson();
        Lessons::saveNote($uid, (int)$l['id'], (string)($_POST['note'] ?? ''));
        json_response(['ok' => true]);
    }
}
