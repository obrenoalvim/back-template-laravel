<?php

return [
    /*
     * Env vars every deploy must set. Checked fail-fast at boot by
     * EnvValidationServiceProvider — extend this list as features
     * (DB, mail, auth) add their own required vars.
     */
    'required' => [
        'APP_KEY',
        'APP_ENV',
        'APP_URL',
        'LOG_CHANNEL',
        'LOG_LEVEL',
    ],
];
