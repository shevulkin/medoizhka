<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Csrf, EmailAuth, AuthLog, Newsletter;

/**
 * Повторне підтвердження перед небезпечними діями (Auth::requireRecent): код на пошту акаунта.
 * Потрібне, бо сесія адміністратора живе години, а зміна ролей чи ключів не повинна відбуватись лише
 * тому, що хтось сів за відкритий комп'ютер або виманив куку.
 */
class Confirm
{
    public static function index(): never
    {
        $u = Auth::user();
        if (!$u) redirect('/');
        $email = Newsletter::normEmail((string)($u['email'] ?? ''));
        $back = (string)($_SESSION['confirm_back'] ?? '/admin');
        if (!str_starts_with($back, '/admin') || str_contains($back, '//')) $back = '/admin';

        if (Auth::confirmedRecently()) redirect($back);

        if (is_post()) {
            Csrf::verify();
            $action = (string)($_POST['_action'] ?? '');
            if ($action === 'send' && $email !== null) {
                $res = EmailAuth::sendCode($email);
                if (!empty($res['ok']) && !empty($res['token'])) {
                    $_SESSION['confirm_login'] = ['token' => $res['token'], 'tries' => 0];
                    flash('success', 'Код надіслано на ' . self::mask($email) . '. Він діє 15 хвилин.');
                } else {
                    flash('error', (string)($res['error'] ?? 'Не вдалося надіслати код. Спробуйте за хвилину.'));
                }
                redirect('/admin/confirm');
            }
            if ($action === 'verify') {
                $st = $_SESSION['confirm_login'] ?? null;
                if (!$st) { flash('error', 'Спершу отримайте код.'); redirect('/admin/confirm'); }
                if ((int)$st['tries'] >= 5) {
                    unset($_SESSION['confirm_login']);
                    AuthLog::write((int)$u['id'], 'login_failed', 'повторне підтвердження: забагато спроб');
                    flash('error', 'Забагато невдалих спроб. Отримайте новий код.');
                    redirect('/admin/confirm');
                }
                $_SESSION['confirm_login']['tries']++;
                $res = EmailAuth::verify((string)$st['token'], (string)($_POST['code'] ?? ''));
                // код має належати саме цьому акаунту, а не будь-якій пошті
                if (!empty($res['ok']) && (int)($res['user_id'] ?? 0) === (int)$u['id']) {
                    unset($_SESSION['confirm_login'], $_SESSION['confirm_back']);
                    Auth::markConfirmed();
                    AuthLog::write((int)$u['id'], 'login', 'повторне підтвердження небезпечної дії');
                    redirect($back);
                }
                flash('error', 'Невірний код.');
                redirect('/admin/confirm');
            }
            redirect('/admin/confirm');
        }

        View::show('admin/confirm', [
            'email' => $email !== null ? self::mask($email) : '',
            'sent' => !empty($_SESSION['confirm_login']),
            'back' => $back,
            'page_title' => 'Підтвердження — Адмінпанель',
        ], 'layouts/admin');
    }

    private static function mask(string $email): string
    {
        [$n, $d] = explode('@', $email, 2) + ['', ''];
        return mb_substr($n, 0, 2) . '***@' . $d;
    }
}