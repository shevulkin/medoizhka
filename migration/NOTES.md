# Медоїжка: що знайдено на старому сайті (2026-09-30)

Стек: WordPress + Astra + WooCommerce + Yoast SEO, LiteSpeed, картинки на cdn.medoizhka.com.
Логотип: https://cdn.medoizhka.com/wp-content/uploads/2025/09/logo-medoizhka.webp (1181px, ТМ, не змінювати).

## Адреси, які треба зберегти
Списки з sitemap Yoast: `urls_*.txt` (page 10, product 43, product_cat 11, product_tag 16, product_brand 5, category 1, author 1).
Блог-записів у sitemap немає, але курси лежать у записах WP (`post`) за кореневими адресами:
`/volynec/`, `/dukarev/`, `/bilko-group-03/` (у sitemap їх немає, вони закриті від індексації).
Кабінет: `/my-account/`, `/my-account/my-course/` (список «Ваше навчання»), `/my-courses/`.
Категорії товарів: `/product-category/{slug}/`. Товари: `/product/{slug}/`, багато слагів кирилицею (percent-encoded).

## Як влаштовані курси (кастомний плагін «mq»)
- Відео: Bunny Stream, бібліотека `573243`, плеєр-iframe
  `https://iframe.mediadelivery.net/embed/573243/{guid}?autoplay=..&token=..&expires=..`
  Токен підписаний на сервері (Bunny token authentication) на кожного глядача з терміном дії.
- Урок = guid відео. Список уроків зберігається в записі (див. `courses.json`).
- Позиція перегляду: плеєр шле `player:timeupdate` через postMessage; кожні 5 с це йде
  на сервер (`mq_save_progress`: video_id, time), дублюється в localStorage `mq_t_{guid}`.
  Щоб продовжити, показується «Ви зупинилися на 12:34» з кнопками «Так» / «Спочатку»
  (`command:seek`, `command:play`).
- «Вивчено»: `mq_toggle_lesson` (video_id) перемикає позначку, у списку клас `is-watched`.
- Нотатки: `mq_save_note` (video_id, note, nonce), автозбереження через 1,2 с після набору,
  особисті для кожного користувача, під відео.
- Кнопки «← Назад / Далі →» між уроками, заголовок уроку над плеєром.
- «Мої курси» в меню видно лише тим, хто купив.

## Курси на сайті
| slug | WP id | назва | уроків |
|---|---|---|---|
| volynec | 995 | С. Волинець, консультант з апітерапії | 37 |
| dukarev | 986 | Аудіокнига Дюкарєва | 19 |
| bilko-group-03 | 1017 | Н. Білько, базовий курс | 15 |

Прогрес, нотатки й хто що купив живуть у БД старого сайту. Щоб перенести їх, потрібен
дамп БД (таблиці плагіну mq і wc_orders / wp_users) або експорт CSV.
