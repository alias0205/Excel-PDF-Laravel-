# Copilot Instructions

## Project overview
- Laravel 12 (PHP 8.2) application; routing is HTTP-first with Blade views (see [routes/web.php](routes/web.php) and [resources/views/welcome.blade.php](resources/views/welcome.blade.php)).
- Frontend assets are built with Vite + Tailwind v4, with entry points in [resources/css/app.css](resources/css/app.css) and [resources/js/app.js](resources/js/app.js).

## Key architecture & flow
- Request flow: routes in [routes/web.php](routes/web.php) return views in resources/views; controllers live in app/Http/Controllers when added.
- Service providers are registered in app/Providers (base stub in [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php)).
- Data layer uses Eloquent models in app/Models (example [app/Models/User.php](app/Models/User.php)).
- Migrations live in database/migrations; default SQLite file is database/database.sqlite (created by composer post-create hook).

## Frontend build details
- Vite config uses `laravel-vite-plugin` + Tailwind plugin (see [vite.config.js](vite.config.js)). Blade uses `@vite(...)` for assets with hot/manifest detection (see [resources/views/welcome.blade.php](resources/views/welcome.blade.php)).
- Tailwind v4 is configured via CSS `@import 'tailwindcss'` and `@source` globs in [resources/css/app.css](resources/css/app.css); keep new Blade/JS/Vue files under resources/** so they are scanned.

## Developer workflows (from repo config)
- Dev stack: `composer run dev` starts `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan pail --timeout=0`, and `npm run dev` concurrently (see composer.json).
- Frontend only: `npm run dev` / `npm run build` (see package.json).
- Tests: `vendor/bin/phpunit` or `php artisan test` (see phpunit.xml).
- Formatting: `vendor/bin/pint` (dev dependency in composer.json).

## Conventions & examples
- Use Blade templates for server-rendered pages; assets are referenced with `@vite` (example in [resources/views/welcome.blade.php](resources/views/welcome.blade.php)).
- Keep asset entry points limited to those defined in [vite.config.js](vite.config.js); add new imports to resources/js/app.js or resources/css/app.css as needed.
