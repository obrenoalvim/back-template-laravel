<?php

return [
    /*
     * Env vars every deploy must set. Checked fail-fast at boot by
     * EnvValidationServiceProvider — extend this list as features (DB,
     * mail, auth) add their own required vars.
     *
     * Keys map to a config() path, not read via env() directly: once
     * `php artisan config:cache` runs (standard in prod), Laravel skips
     * re-parsing .env entirely, so env() outside a config file returns
     * whatever the real OS env happens to hold at that moment — not
     * necessarily what was baked into the cache. config() always reflects
     * what was actually resolved at cache time, cached or not.
     */
    'required' => [
        'APP_KEY' => 'app.key',
        'APP_ENV' => 'app.env',
        'APP_URL' => 'app.url',
        'LOG_CHANNEL' => 'logging.default',
        'LOG_LEVEL' => 'logging.channels.single.level',
        'DB_CONNECTION' => 'database.default',
        'DB_HOST' => 'database.connections.pgsql.host',
        'DB_PORT' => 'database.connections.pgsql.port',
        'DB_DATABASE' => 'database.connections.pgsql.database',
        'DB_USERNAME' => 'database.connections.pgsql.username',
        'MAIL_MAILER' => 'mail.default',
        'MAIL_FROM_ADDRESS' => 'mail.from.address',
        'FRONTEND_URL' => 'app.frontend_url',
    ],
];
