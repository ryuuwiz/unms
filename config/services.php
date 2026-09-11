<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
        'env' => env('XENDIT_ENV', 'development'),
    ],

    'gowa' => [
        'host' => rtrim((string) env('GOWA_HOST', 'http://localhost:3000'), '/'),
        'username' => env('GOWA_USERNAME', ''),
        'password' => env('GOWA_PASSWORD', ''),
        'number' => env('GOWA_NUMBER', ''),
    ],

    'waha' => [
        'host' => rtrim((string) env('WAHA_HOST', 'https://waha.gobilling.id'), '/'),
        'session' => env('WAHA_SESSION', 'gobilling'),
        'api_key' => env('WAHA_API_KEY', '137ae04e09ee4c668430c660db0741f9'),
        'username' => env('WAHA_USERNAME', 'admin'),
        'password' => env('WAHA_PASSWORD', '81f6bafc11b34793b4349034cbb60178'),
        'number' => env('WAHA_NUMBER', '08970919525'),
    ],

    'ipaymu' => [
        'va' => env('IPAYMU_VA', ''),
        'api_key' => env('IPAYMU_API_KEY', ''),
        'env' => env('IPAYMU_ENV', 'sandbox'),
    ],

];
