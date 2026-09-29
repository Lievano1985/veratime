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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'brevo' => [
        'enabled' => env('BREVO_ENABLED', false),
        'api_key' => env('BREVO_API_KEY'),
        'endpoint' => env('BREVO_API_ENDPOINT', 'https://api.brevo.com/v3/smtp/email'),
        'timeout' => env('BREVO_API_TIMEOUT', 10),
        'sender' => [
            'address' => env('BREVO_SENDER_ADDRESS'),
            'name' => env('BREVO_SENDER_NAME', env('MAIL_FROM_NAME')),
        ],
        'contact_recipient' => env('BREVO_CONTACT_RECIPIENT'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
