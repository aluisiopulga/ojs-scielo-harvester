<?php

/**
 * Copy this file to config/journals.php and fill in your journals.
 *
 * config/journals.php is git-ignored. Prefer environment variables for tokens
 * so they never end up in a file at all:
 *
 *   export OJS_TOKEN_JHS="eyJ0eXAiOiJKV1Qi..."
 *
 * An OJS API token is created in OJS under: User profile -> API Key.
 * The user must have an editor or journal manager role in that journal,
 * otherwise the API answers 200 with an empty list.
 */

return [
    'http' => [
        // Identify your harvester honestly. Some sites block unknown agents;
        // if that happens, set a browser-like user agent here.
        'user_agent' => 'ojs-scielo-harvester/1.0 (+https://github.com/your-org/your-site)',
        'delay_seconds' => 0.5,   // pause between requests
        'max_retries' => 2,       // retries on network errors, 429 and 5xx
        'timeout' => 60,
        'verify_tls' => true,     // keep it on; point ca_bundle to a CA file if needed
        'ca_bundle' => null,      // e.g. 'C:/wamp64/bin/php/cacert.pem' on WampServer
    ],

    'ojs' => [
        [
            'code' => 'jhs',
            'name' => 'Journal of Health Sciences',
            'publisher' => 'Example University Press',
            'api_url' => 'https://journals.example.org/index.php/jhs/api/v1',
            'token' => getenv('OJS_TOKEN_JHS') ?: '',
            // Set to true if your server strips the Authorization header.
            'token_in_query' => false,
        ],
        [
            'code' => 'rde',
            'name' => 'Review of Digital Education',
            'publisher' => 'Example Institute',
            'api_url' => 'https://rde.example.org/index.php/rde/api/v1',
            'token' => getenv('OJS_TOKEN_RDE') ?: '',
        ],
    ],

    'scielo' => [
        [
            // The acronym used in SciELO URLs: https://www.scielo.br/j/{acronym}/grid
            'acronym' => 'abc',
            'name' => 'Example Journal on SciELO',
            'publisher' => 'Example Society',
            'base_url' => 'https://www.scielo.br',
        ],
    ],
];
