# syntax=docker/dockerfile:1

FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# No env-dependent build step here (unlike a frontend baking
# NEXT_PUBLIC_*/API_BASE_URL into a JS bundle) — this is a backend-only
# API, so nothing in the image needs a placeholder env var. config/route
# caching happens at container start (docker/entrypoint.sh) once real
# runtime env is actually available, deliberately avoiding a build-time
# `config:cache` here: that would bake whatever APP_KEY was present at
# build time into bootstrap/cache/config.php, and Laravel would keep
# using that baked value forever afterward regardless of the real
# APP_KEY passed in at runtime — silently breaking encryption/signed
# URLs the moment build-time and runtime keys diverge.
#
# --no-scripts on dump-autoload skips Laravel's own post-autoload-dump
# hook (artisan package:discover), which boots the framework and would
# otherwise hit EnvValidationServiceProvider with no env present yet —
# package:discover instead runs in the entrypoint, alongside config/
# route caching, once real env is available. No placeholder env vars
# needed anywhere in this image.
FROM dunglas/frankenphp:1-php8.4-bookworm AS builder
WORKDIR /app
RUN install-php-extensions pdo_pgsql pgsql opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=deps /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

FROM dunglas/frankenphp:1-php8.4-bookworm AS runtime
RUN install-php-extensions pdo_pgsql pgsql opcache \
    && apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=builder /app /app
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

ENV XDG_CONFIG_HOME=/home/appuser/.config \
    XDG_DATA_HOME=/home/appuser/.local/share

RUN useradd -m -d /home/appuser appuser \
    && mkdir -p "$XDG_CONFIG_HOME/caddy" "$XDG_DATA_HOME/caddy" \
    && chown -R appuser:appuser /app/storage /app/bootstrap/cache /home/appuser \
    && chmod +x /usr/local/bin/entrypoint.sh

USER appuser
EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=5 \
    CMD curl -f http://127.0.0.1:8000/api/health || exit 1

ENTRYPOINT ["entrypoint.sh"]
