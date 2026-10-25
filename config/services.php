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

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'semaphore' => [
            'url' => env('SEMAPHORE_URL', 'https://api.semaphore.co/api/v4'),
            'key' => env('SEMAPHORE_API_KEY'),
            'sender' => env('SEMAPHORE_SENDER_NAME'),
            'timeout' => env('SEMAPHORE_TIMEOUT', 15),
        ],
    ],

    'huggingface' => [
        'url' => env('HF_API_URL', 'https://router.huggingface.co/hf-inference/models/dima806/ai_vs_human_generated_image_detection'),
        'token' => env('HF_TOKEN'),
        'timeout' => env('HF_TIMEOUT_SECONDS', 60),
    ],

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

];
