English | [Português](README.pt.md)

# back-template-laravel

Production-ready base template for new backend projects: Laravel 13 (PHP 8.4), PostgreSQL + Eloquent, Sanctum token auth with email verification/password reset/rate limiting, transactional mail with a console fallback, structured logging, a full example CRUD resource (`notes`), and Docker + CI wired end to end.

## Stack

- Laravel 13, PHP 8.4, `strict_types` throughout
- PostgreSQL + Eloquent (versioned migrations in `database/migrations`)
- Laravel Sanctum: personal access tokens (Bearer, not SPA cookie mode, framework-agnostic for any frontend)
- Laravel Mail: `log` driver in dev (writes to `storage/logs` instead of requiring real SMTP creds)
- Monolog: pretty logs in dev, JSON to stdout in prod, level via `LOG_LEVEL`
- Form Requests: schema validation on every client-input endpoint
- Pest (unit, no DB) + Pest (feature/integration, real Postgres, no mocks)
- Pint (format) + Larastan/PHPStan (lint) + a plain-shell pre-commit hook (no Node/Husky)
- Docker (multi-stage, FrankenPHP runtime, non-root, healthcheck) + docker-compose
- GitHub Actions (build+lint+unit, e2e against a real Postgres service, Docker build+smoke-test) + Dependabot

## Getting started (Docker, recommended)

```bash
cp .env.example .env
php -r "file_put_contents('.env', str_replace('APP_KEY=', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), file_get_contents('.env')));"

composer docker:up          # builds and starts db + app
```

App: http://localhost:8080/api. Postgres is exposed on host port `5457` by default; change `POSTGRES_PORT` in `.env` if that collides locally.

## Getting started (without Docker)

Requires a reachable Postgres instance (`docker compose up -d db` starts just that) and PHP 8.4 with `pdo_pgsql`/`pgsql` enabled.

```bash
cp .env.example .env
php -r "file_put_contents('.env', str_replace('APP_KEY=', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), file_get_contents('.env')));"
composer install
composer db:migrate
composer dev
```

## Environment variables

See `.env.example` for the full, commented list. `config/env.php` + `app/Providers/EnvValidationServiceProvider` validate the required ones at boot: a missing/empty var fails fast with a readable message, whether or not `php artisan config:cache` has run (see Design notes).

## Auth

- `POST /api/auth/register`, `POST /api/auth/login`: both throttled to 5 requests/60s per IP, fixed and not env-configurable (see Design notes)
- `GET /api/auth/verify-email/{id}/{hash}` (signed URL, emailed on register), `POST /api/auth/email/resend`
- `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`
- `GET /api/account` (current user), `PUT /api/account/password`, `DELETE /api/account` (password change/delete both require the current password too)
- `POST /api/auth/refresh`, `POST /api/auth/logout`: see [Sessions](#sessions) below
- Changing your password revokes every *other* token
- All protected routes use the `auth:sanctum` middleware
- `GET /api/routes` lists every registered `/api/*` route (method, URI, name), a dev convenience for exploring the template, not for production traffic

## Sessions

`register`/`login`/`refresh` return `{ user?, accessToken, refreshToken }`. Both are real Sanctum personal access tokens (see `AuthController::issueTokenPair()`) with different **abilities** and TTLs. `access` (`SANCTUM_ACCESS_TTL_MINUTES`, default 60) is what you send as `Authorization: Bearer <accessToken>` on normal requests; `refresh` (`SANCTUM_REFRESH_TTL_DAYS`, default 30) can *only* authenticate `POST /api/auth/refresh`, checked via `tokenCan('refresh')`. An access token gets a 403 if you try to use it there.

`refresh` **rotates**: the presented refresh token is deleted the moment a new pair is issued, so a stolen-and-replayed one stops working right after the legitimate client's next refresh. `logout` revokes the current token and, if you pass `refresh_token` in the body, that one too. Send both so logout doesn't leave a live refresh token behind.

This is Sanctum's own ability-scoped, per-token-expiration feature (no parallel JWT system bolted on); see `hasinhayder/hydra` for the same pattern taken further (roles as abilities too).

## Roles

Every user has a `role` column (`'user'` | `'admin'`, default `'user'`), deliberately absent from `User`'s `#[Fillable(...)]` list so it can never be set via register/update-profile input. `GET /api/admin/users` (admin-only, list of all users) is the reference for protecting a route: the `admin` middleware alias (`app/Http/Middleware/EnsureUserIsAdmin.php`, registered in `bootstrap/app.php`) throws an `AuthorizationException` (403) for non-admins. No self-serve promotion: flip the column directly (`UPDATE users SET role = 'admin' WHERE email = '...'`) for local testing.

## Email

Verification and password-reset mail go through Laravel's built-in notifications (`VerifyEmail`/`ResetPassword`), reconfigured to link at `FRONTEND_URL` for the reset flow (verification links straight to the API's own signed route). Without SMTP configured (`MAIL_MAILER=log`), mail is written to `storage/logs/laravel.log` instead of requiring real credentials to try the flow locally.

## Example CRUD resource

`app/Http/Controllers/NotesController.php` + `app/Models/Note.php` (owned by the authenticated user, Form-Request-validated, full CRUD) is the reference implementation to copy for your first real feature. Delete it once you don't need the reference (drop the `notes` migration/model/controller/requests, generate a migration to drop the table).

## API documentation

OpenAPI docs are generated from `zircote/swagger-php` attributes (`#[OA\...]`) on controllers, via [L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger). With the app running, open `/api/documentation` for the interactive Swagger UI: `http://localhost:8000/api/documentation` via `composer dev`, `http://localhost:8080/api/documentation` via Docker. Use the "Authorize" button with a Sanctum token from `/api/auth/login` to try protected routes (`notes`, `account`, `auth/logout`, `auth/email/resend`).

`L5_SWAGGER_GENERATE_ALWAYS=true` (set in `.env.example`) regenerates the spec on every request in dev; unset or `false` in production and run `php artisan l5-swagger:generate` as a build/deploy step instead.

## Testing

- **Unit** (`vendor/bin/pest --testsuite=Unit`): no database (e.g. `ApiExceptionRendererTest` instantiates the renderer directly).
- **Feature/integration** (`composer test:e2e`): real Postgres via `RefreshDatabase`, no mocking. Requires `docker compose up -d db` (or a reachable Postgres matching `phpunit.xml`'s `DB_*`) first.
- `composer test` runs everything together (what CI's `build`/`e2e` jobs split into two separate jobs instead).

## Docker

- `Dockerfile`: 3 stages (`composer:2` deps → FrankenPHP `builder` → FrankenPHP `runtime`), runs as a non-root user on unprivileged port `8000`, healthcheck hits `/api/health` via `127.0.0.1`.
- `docker/entrypoint.sh`: runs `package:discover`/`config:cache`/`route:cache`/`event:cache` at container **start**, not image build (see Design notes), then execs FrankenPHP.
- `docker-compose.yml`: `db` (Postgres 17, healthchecked via `pg_isready`, host port `5457`) and `app` (waits for `db` healthy, host port `8080`).

## Scripts

| Script | Purpose |
| --- | --- |
| `composer dev` | Start dev server (`artisan serve`) |
| `composer build` | `composer install --no-dev --optimize-autoloader` |
| `composer start` | Cache config/routes, then serve (local approximation of the Docker entrypoint) |
| `composer lint` | Larastan (`phpstan analyse`) |
| `composer format` | Pint (write) |
| `composer format:check` | Pint (check only) |
| `composer test` | Full Pest suite (Unit + Feature) |
| `composer test:watch` | Unit tests, re-run every 2s (Pest has no native watch mode, see Design notes) |
| `composer test:e2e` | Feature tests only, against real Postgres |
| `composer db:generate` | `artisan make:migration` |
| `composer db:migrate` | `artisan migrate` |
| `composer docker:up` | `docker compose up -d` |
| `composer docker:down` | `docker compose down` |

No `db:studio`: Eloquent has no bundled visual data browser the way Prisma/Drizzle do, use `psql`, TablePlus, or similar against the Postgres container directly.

## Using this as a template

1. Clone/degit this repo as your new project's starting point
2. Update `composer.json`'s `name`/`description` and this README
3. `cp .env.example .env`, generate a real `APP_KEY` (see Getting started)
4. `composer docker:up && composer db:migrate`
5. Delete `NotesController`/`Note` once you've copied its pattern for your own first feature

## Design notes and gotchas

Things that weren't obvious while building this, kept here so they don't have to be rediscovered:

- **`env()` outside a config file breaks the moment `php artisan config:cache` runs**: Laravel skips re-parsing `.env` entirely once config is cached (a standard prod optimization), so `env()` calls elsewhere then read whatever's in the real OS environment at that instant, not necessarily what `.env` would have provided. `EnvValidationServiceProvider`'s fail-fast check originally used `env()` directly, meaning it would have reported every required var as "missing" on any cached-config boot. Fixed by mapping `config/env.php`'s required list to `config()` paths (e.g. `APP_KEY` → `app.key`) instead, which are correctly resolved once at cache time either way.
- **Laravel's own exception handler rewraps `ModelNotFoundException`/`AuthorizationException` before any custom `render()` callback runs**: `Handler::prepareException()` converts them into `NotFoundHttpException`/`AccessDeniedHttpException` with the original as `$previous`, so `instanceof ModelNotFoundException` in `ApiExceptionRenderer` was silently dead code, falling through to the generic branch and leaking the raw Eloquent message ("No query results for model [App\Models\Note] 99999") instead of a clean "Resource not found." Fixed by also checking `$e->getPrevious()`.
- **A protected route crashes with 500, not 401, if the request doesn't send `Accept: application/json`**: Laravel's `Authenticate` middleware calls `route('login')` to build the exception's redirect target for non-JSON clients (plain curl, browsers), and `route('login')` throws `RouteNotFoundException` immediately if no such route exists, which this API-only app didn't have. Fixed with a dummy named `login` route (`routes/web.php`) that just `abort(401)`s; JSON clients never actually reach it since `shouldRenderJsonWhen()` renders the 401 first.
- **`composer dump-autoload`'s `post-autoload-dump` hook (`artisan package:discover`) boots the framework**: with no env present yet during a Docker build, that hit `EnvValidationServiceProvider` and killed the build. Fixed with `--no-scripts` on the build-stage `dump-autoload`; `package:discover` instead runs in `docker/entrypoint.sh` alongside cache warming, once real runtime env exists. The same ordering bug hit CI directly (composer's own `install` script, not just the Docker build). Fixed by generating `APP_KEY` with plain `php -r` (no Laravel needed) *before* `composer install` runs at all.
- **No Docker build-time placeholder env vars, on purpose**: unlike a frontend baking `NEXT_PUBLIC_*`/`API_BASE_URL` into a JS bundle at build time, a backend API has no such build-time env dependency, so config/route/event caching happens in `docker/entrypoint.sh` at container **start**, using real runtime env, not at build time. Baking `config:cache` at build time with a placeholder `APP_KEY` would make Laravel keep using that placeholder forever afterward regardless of the real key passed in at runtime, silently breaking encryption and signed URLs the moment they diverge.
- **`bootstrap/cache/*.php` is gitignored but wasn't dockerignored**: a locally-generated dev-time package-discovery manifest (listing `laravel/pail`'s provider, a dev-only package absent from the `--no-dev` image) got copied into the image via `COPY . .` and crashed *every* artisan invocation in the container: that stale manifest loads before `package:discover` ever gets a chance to regenerate it. Fixed by adding `bootstrap/cache/*.php` to `.dockerignore`.
- **This Larastan version doesn't understand Laravel 11+'s method-based `casts()`**: it silently inferred `email_verified_at` as `string` instead of `Carbon`, correctly special-casing only `created_at`/`updated_at` (confirmed with `PHPStan\dumpType()`). Fixed by keeping `User::$casts` as the classic property instead of a method; its Eloquent extension does parse that form.
- **`composer.lock`, resolved on a PHP 8.4 machine, had already pulled in `symfony/clock 8.1` requiring PHP `>=8.4.1`**, meaning the declared `"php": "^8.3"` was a lie before Docker ever caught it (Docker's PHP 8.3 image failed `composer dump-autoload` with a platform-requirements error). Bumped `composer.json` and the Docker base image to PHP 8.4 to match what had actually been tested all along, rather than chasing 8.3-compatible dependency versions.
- **Postgres host port `5457`, not `5432`/`5455`/`5456`**: this machine already has other local Postgres instances (and the sibling `back-template-nest`/`next-template` projects) bound to those. `POSTGRES_PORT` in `.env` makes it a one-line fix wherever this is deployed.
- **Register/login's rate limit is a fixed `throttle:5,1` route middleware, not env-driven**: deliberately not wired to an env var, so a misconfigured `.env` can't accidentally loosen protection on the two endpoints most worth protecting (same reasoning as the sibling Nest template).
- **Pest has no built-in watch mode**: `test:watch` polls `artisan test --testsuite=Unit` every 2 seconds instead of reacting to file changes. A real filesystem watcher needs a plugin with a native `fswatch`/`inotify` dependency, not worth adding for one dev-convenience script. It's honestly a poll, not a watch.
- **Sequential requests inside one Pest test can authenticate with an already-revoked token**: Laravel's `AuthManager` caches resolved guard instances (and `RequestGuard` caches its resolved user on itself) for the app's lifetime. That's fine in production, where every real request boots a fresh app, but within one test making several requests through the same booted app, a token revoked by request *N* could still "authenticate" on request *N+1*. Not a production bug, but real enough to break 3 tests until `forgetAuthGuards()` (`tests/Pest.php`) was called after every auth-state-changing request.
