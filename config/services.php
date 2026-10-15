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

    'sms_alert' => [
        'enabled' => env('SMS_ALERT_ENABLED', false),
        'storage_ready' => env('FRANCHISE_OTP_STORAGE_READY', false),
        'api_key' => env('SMS_ALERT_API_KEY'),
        'sender' => env('SMS_ALERT_SENDER'),
    ],

    'zerobounce' => [
        'api_key' => env('ZEROBOUNCE_API_KEY'),
    ],

    'prime' => [
        'submission_enabled' => env('PRIME_SUBMISSION_ENABLED', false),
    ],

    'enquiry' => [
        'submission_enabled' => env('ENQUIRY_SUBMISSION_ENABLED', false),
        'email_enabled' => env('ENQUIRY_EMAIL_ENABLED', false),
    ],

    'fofo' => [
        'submission_enabled' => env('FOFO_SUBMISSION_ENABLED', false),
        'call_enabled' => env('FOFO_CALL_ENABLED', false),
        'call_url' => env('FOFO_CALL_URL'),
    ],

];
