# Draw

Draw is a team whiteboard where people and agents can sketch together in real time. It is built around the MIT-licensed [`@excalidraw/excalidraw`](https://github.com/excalidraw/excalidraw) package.

The MVP includes board editing, view and edit sharing, signed viewer links, live updates, and a delegated MCP tool surface.

## Built for Cloud

Draw is intended for installation through [its Scalpels product page](https://scalpels.app/products/draw). Scalpels supplies managed identity and provisions the PostgreSQL database, queue, and managed WebSockets declared in `built-for-cloud.json`. Laravel Cloud injects their configuration, so it must not be copied into local environment files.

## Local development

Draw needs PHP 8.3 or newer, Composer, Node 22, npm, and PostgreSQL. Create local databases named `draw` and `draw_app_test`, then run:

```bash
composer install --no-interaction --prefer-dist
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

The test configuration uses `draw_app_test` on PostgreSQL at `127.0.0.1`. It does not fall back to SQLite.

## Checks

Run the focused scaffold smoke test with:

```bash
php artisan test tests/Feature/ScaffoldTest.php
```

The complete gate is:

```bash
composer ready
```

That command regenerates IDE helpers, runs Rector, Pint, PHPStan, Pest, and the Composer audit, then installs, lints, and builds the frontend with npm.

## Deployment

The standard Built for Cloud build runs `composer install`; Draw’s Composer post-install hook then runs `npm ci` and `npm run build` without a wrapper script. `built-for-cloud.json` asks Scalpels for PostgreSQL, a managed queue, and managed WebSockets, then runs the database migrations after deployment.

## Excalidraw

Draw uses Excalidraw as an upstream drawing technology and does not claim its name or logo. Teams looking for Excalidraw's hosted collaboration and AI features should see [Excalidraw+](https://plus.excalidraw.com). The upstream license notice is in `THIRD_PARTY_NOTICES.md`.

## Repository

The source repository is [artisan-build/draw-app](https://github.com/artisan-build/draw-app).
