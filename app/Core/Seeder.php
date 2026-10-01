<?php
declare(strict_types=1);

/**
 * Початкові дані Медоїжки.
 *
 * Демо-товарів тут немає навмисно: каталог приходить зі старого сайту
 * (bin/import-wp.php), курси й уроки — з bin/import-courses.php. Сідер ставить
 * лише те, без чого система не працює: налаштування, правила сповіщень, першого
 * адміністратора, власний бренд і одну точку продажу.
 */
class Seeder
{
    public static function run(): void
    {
        if (DB::val('SELECT COUNT(*) FROM users') > 0) { echo "Seed: дані вже є, пропускаю\n"; return; }

        $store = DB::insert('stores', [
            'name' => 'Пасіка Медоїжка', 'slug' => 'pasika-medoizhka', 'city' => 'Україна',
            'address' => '', 'phone' => '', 'hours' => 'Пн–Сб 9:00–18:00', 'active' => 1, 'sort' => 1,
        ]);

        // Перший адміністратор. Входить за кодом з пошти або через Google, пароля немає.
        $admin = DB::insert('users', [
            'email' => 'yevgenii.vasylenko@gmail.com', 'name' => 'Адміністратор', 'role' => 'admin',
            'active' => 1, 'created_at' => now(),
        ]);
        DB::insert('user_roles', ['user_id' => $admin, 'role' => Roles::ADMIN, 'created_at' => now()]);

        DB::insert('brands', [
            'name' => Catalog::ownBrandName(), 'slug' => 'medoizhka', 'own' => 1, 'active' => 1, 'sort' => 0,
        ]);

        $settings = [
            'notify_all_enabled' => '1', 'notify_telegram_enabled' => '1',
            'notify_email_enabled' => '1', 'notify_push_enabled' => '1', 'notify_viber_enabled' => '1',
            'sale_banner_active' => '0', 'sale_banner_text' => '', 'sale_banner_percent' => '0',
            // Поки йде перенесення, сайт закритий від пошуковиків. Вмикається одним
            // прапорцем в адмінці в день, коли домен перемикається на новий сайт.
            'seo_noindex' => '1',
            'telegram_bot_token' => '', 'viber_bot_token' => '', 'np_api_key' => '',
            'mail_from' => '', 'mail_from_auth' => '', 'mail_reply_to' => '',
            'google_client_id' => '', 'google_client_secret' => '',
            // Bunny Stream: бібліотека відео й ключ підписаних посилань (Security → Token Authentication)
            'bunny_library_id' => '573243', 'bunny_token_key' => '',
            // Заголовок і опис головної — такі, як були проіндексовані на старому сайті
            'seo_title' => 'Медоїжка - це сімейна медова крамниця - Медоїжка',
            'seo_description' => 'Медоїжка - крамниця натурального меду апіпродуктів з власної пасіки. Мед, пилок, прополіс, креми, свічки, настоянки, крем, віск, вощина.',
            'schema_version' => (string)Schema::VERSION,
        ];
        foreach ($settings as $k => $v) { DB::query("DELETE FROM settings WHERE `key` = ?", [$k]); DB::insert("settings", ["key" => $k, "value" => $v]); }

        foreach (Notify::EVENTS as $event => $label) {
            [$to, $on] = Notify::DEFAULT_RULES[$event] ?? ['admins_sellers', false];
            foreach (array_keys(Notify::CHANNELS) as $channel) {
                DB::insert('notification_rules', [
                    'event' => $event, 'channel' => $channel,
                    'enabled' => $on ? 1 : 0,
                    'recipients' => $to, 'template' => Notify::DEFAULT_TEMPLATES[$event] ?? '',
                ]);
            }
        }
        echo "Seed: готово (точка #$store, адмін #$admin)\n";
    }
}
