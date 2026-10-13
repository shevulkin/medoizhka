<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth;

class Dashboard
{
    public static function index(): never
    {
        // Адмін рахує замовлення цілком (головні), продавець — свої підзамовлення:
        // рахувати обидва рівні разом означало б подвоїти і кількість, і оборот.
        $storeIds = Auth::storeIds();
        $in = $storeIds ? implode(',', array_map('intval', $storeIds)) : '0';
        $where = Auth::can('orders.manage') ? 'parent_id IS NULL' : "parent_id IS NOT NULL AND store_id IN ($in)";
        $stats = [
            'orders_new' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status = 'new' AND $where"),
            'orders_total' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE $where"),
            'products' => (int)DB::val('SELECT COUNT(*) FROM products WHERE active = 1'),
            'revenue' => (float)(DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'canceled' AND $where") ?? 0),
        ];
        $recent = DB::all("SELECT * FROM orders WHERE $where ORDER BY id DESC LIMIT 8");
        // Підказка про безпеку входу: кожен ввімкнений спосіб — це ще один шлях в акаунт персоналу
        $loginOn = [];
        if ($u = Auth::user()) foreach (\LoginMethods::forUser($u) as $m) if (!empty($m['on'])) $loginOn[] = $m['label'];
        View::show('admin/dashboard', [
            'login_on' => $loginOn,
            'stats' => $stats, 'recent' => $recent,
            // Стан фонових завдань бачить лише той, хто керує налаштуваннями
            'jobs' => Auth::can('settings.manage') ? \Scheduler::status() : null,
            'page_title' => 'Огляд — Адмінпанель',
        ], 'layouts/admin');
    }
}
