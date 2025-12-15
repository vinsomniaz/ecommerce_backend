<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'document_validation' => [
        'provider' => env('DOCUMENT_VALIDATION_PROVIDER', 'decolecta'),
    ],

    'apisperu' => [
        'token' => env('APISPERU_TOKEN'),
        'base_url' => 'https://dniruc.apisperu.com/api/v1',
    ],

    'decolecta' => [
        'token' => env('DECOLECTA_TOKEN'),
        'base_url' => env('DECOLECTA_BASE_URL', 'https://api.decolecta.com'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],
];
