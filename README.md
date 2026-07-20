# Калькулятор поездок

Laravel 13 + Inertia + Vue 3 приложение для учёта расходов поездки и расчёта «кто кому должен». Без авторизации.

## Требования

- PHP 8.3+
- Composer
- Node.js 20+ (в репозитории можно использовать `.tools/node-v22.17.0-win-x64`, если установлен)

## Установка

```bash
composer install
copy .env.example .env   # Windows: copy .env.example .env
php artisan key:generate
# убедитесь, что DB_CONNECTION=sqlite и есть файл database/database.sqlite
type nul > database\database.sqlite   # Windows
php artisan migrate
npm install
npm run build
```

## Запуск

В двух терминалах:

```bash
php artisan serve
npm run dev
```

Или одной командой (если настроен `composer dev`):

```bash
composer dev
```

Откройте http://127.0.0.1:8000

## Возможности

- Создание поездки с 2+ путешественниками
- Выбор существующей поездки
- Добавление расходов (бензин, платная дорога, жильё, другое)
- Расчёт балансов и подсказок «кто кому должен»
