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

    'zalo_oa' => [
        'access_token' => env('ZALO_OA_ACCESS_TOKEN'),
        'message_url' => env('ZALO_OA_MESSAGE_URL'),
        'webhook_token' => env('ZALO_OA_WEBHOOK_TOKEN'),
    ],

    'zalo_zns' => [
        'access_token' => env('ZALO_ZNS_ACCESS_TOKEN', env('ZALO_OA_ACCESS_TOKEN')),
        'access_token_expires_at' => env('ZALO_ZNS_ACCESS_TOKEN_EXPIRES_AT'),
        'refresh_token' => env('ZALO_ZNS_REFRESH_TOKEN'),
        'refresh_enabled' => env('ZALO_ZNS_REFRESH_ENABLED', env('APP_ENV') === 'production'),
        'api_key' => env('ZALO_ZNS_API_KEY'),
        'endpoint' => env('ZALO_ZNS_ENDPOINT', 'https://business.openapi.zalo.me/message/template'),
        'template_id' => env('ZALO_ZNS_TEMPLATE_ID'),
        'token_endpoint' => env('ZALO_ZNS_TOKEN_ENDPOINT', 'https://oauth.zaloapp.com/v4/oa/access_token'),
        'app_id' => env('ZALO_ZNS_APP_ID'),
        'app_secret' => env('ZALO_ZNS_APP_SECRET'),
        'refresh_before_seconds' => env('ZALO_ZNS_REFRESH_BEFORE_SECONDS', 300),
        'verify_ssl' => env('ZALO_ZNS_VERIFY_SSL', true),
    ],

];
