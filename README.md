# Медоїжка — новий сайт

Заміна WordPress-сайту medoizhka.com на PHP-сайт із тими самими адресами (індексація лишається).
Основа — магазин bofu (кошик, оплата, адмінка, ролі), поверх нього: навчання з Bunny, апітерапевти,
пасіки й апібудиночки, заявки, перенесення каталогу зі старого сайту.

## Запуск локально

```
docker compose up -d                 # база: MySQL на порту 3309, phpMyAdmin на 8083
php bin/cli.php migrate              # таблиці
php bin/cli.php seed                 # налаштування, правила сповіщень, адмін (yevgenii.vasylenko@gmail.com)
php bin/import-wp.php                # каталог, категорії, теги, бренди, сторінки зі старого сайту
php bin/import-courses.php           # 3 курси й 70 уроків (migration/courses.json)
php bin/seed-content.php             # контакти й соцмережі
php -S localhost:8090 router.php     # сайт: http://localhost:8090
```

Вхід: «Увійти» → «Увійти за поштою». Код локально лежить у `storage/logs/php-error.log`
(`grep "EmailAuth: код" storage/logs/php-error.log | tail -1`).
Демо-картки апітерапевтів і пасік (усі з позначкою «Приклад»): `php bin/cli.php demo`; видаляються в адмінці.

## Адреси зі старого сайту

Усі 85 адрес зі sitemap Yoast відповідають 200 на тих самих шляхах (`/product/…/`, `/product-category/…/`,
`/product-tag/…/`, `/brand/…/`, `/about-us/`, `/pasika-medoizhka/` тощо), зі скісною в кінці та відсотковими
кодами для кирилиці. Title, description і canonical беруться з Yoast. Адреси автора й блог-категорії
перенаправляються 301. Вручну редіректи додаються в таблицю `redirects`.

## Курси (Bunny Stream)

- Курс — товар `type=course` зі старим кореневим слагом: `/volynec/`, `/dukarev/`, `/bilko-group-03/`.
- Уроки — `course_lessons` (guid відео з Bunny). Позиція, «вивчено», нотатки — на людину й урок
  (`lesson_progress`, `lesson_notes`). «Мої курси» — `/my-account/my-course/`.
- Підписані посилання: адмінка → «Уроки курсів і Bunny» → ключ Token Authentication бібліотеки 573243.
  Без ключа відео відкриваються непідписаними (працює, лише поки в Bunny не ввімкнено захист токеном).
- Видати доступ вручну: адмінка → «Доступ до курсів» або `php bin/cli.php grant-course пошта слаг|all`.

## Перед запуском на домен

1. Адмінка → Налаштування: вимкнути `seo_noindex` (зараз сайт закритий від пошуковиків).
2. Ключ Bunny, ціни курсів, оплата (еквайринг), Нова Пошта, пошта/Telegram.
3. Замінити демо-картки справжніми; заповнити `legal_entity` у контенті.
4. `php bin/cli.php prod-check`.
5. Перенести покупців курсів: дамп БД старого сайту (або список пошт) → `grant-course`.

## SEO і збереження адрес

- **Старі адреси.** Усі 85 адрес зі sitemap Yoast відкриваються на тих самих шляхах із тим самим title,
  description і canonical. Перевірка: `php bin/seo-audit.php https://medoizhka.com` (після перемикання домену
  порівнює новий сайт зі збереженими даними; звіт — `storage/logs/seo-audit.txt`).
- **Технічні адреси WordPress не переносяться** (`/wp-content/`, `*-sitemap.xml`, `/feed/`, `/page/N/`, `/wp-login.php`, автор): нова структура своя, вони віддають 404. Переносяться адреси товарів (і поки що категорій та сторінок).
- **Автоматичне SEO** для всього нового: title за шаблоном Yoast «Назва - Медоїжка», description з SEO-поля →
  короткого опису → тексту (до 158 символів), canonical, Open Graph, JSON-LD (Product з ціною й наявністю,
  BreadcrumbList, Organization, WebSite), автоматичний `sitemap.xml`, `robots.txt`.
- **Зміна адреси** бренду в адмінці створює 301 зі старої адреси (`Redirects::add`). Слаги товарів і категорій
  після створення не змінюються. Ручні переадресації — таблиця `redirects`.
- **При запуску** вимкнути `seo_noindex` (Налаштування) — інакше сайт закритий від пошуковиків.

## Розгортання на хостинг (cPanel)

Репозиторій: https://github.com/shevulkin/medoizhka.git. Тека сайту: `~/public_html/medoizhka-v2`
(адреса `https://medoizhka.com/medoizhka-v2/`, поруч зі старим сайтом).

1. cPanel → **Git Version Control** → Create → Clone URL `https://github.com/shevulkin/medoizhka.git`,
   Repository Path, наприклад `~/repositories/medoizhka` (НЕ в public_html).
2. **Manage → Pull or Deploy → Deploy HEAD Commit.** `.cpanel.yml` скопіює код у `~/public_html/medoizhka-v2`
   (rsync із прихованими `.htaccess`, без `.git`, `docker-compose.yml`, `router.php`).
3. cPanel → **MySQL Databases**: створити базу й користувача, дати йому ALL PRIVILEGES.
4. У `~/public_html/medoizhka-v2/` створити `config.local.php` за зразком `config.local.example.php`.
5. cPanel → **Terminal**: `cd ~/public_html/medoizhka-v2 && php bin/install.php` — таблиці, налаштування,
   каталог із фото зі старого сайту, курси, контакти. Старий сайт має бути доступний у цей момент.
6. Відкрити `https://medoizhka.com/medoizhka-v2/`. Вхід адміна — «Увійти за поштою» на yevgenii.vasylenko@gmail.com
   (лист піде, коли в адмінці → Налаштування буде задано пошту відправника).

Далі кожне оновлення: push у GitHub → у cPanel «Update from Remote» → «Deploy HEAD Commit».
Схема бази оновлюється сама при першому відкритті сайту. Фото, завантажені в адмінці, і `config.local.php`
розгортання не чіпає.
