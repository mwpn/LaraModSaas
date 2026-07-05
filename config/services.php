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

    'interactive_qris' => [
        'base_url' => env('INTERACTIVE_QRIS_BASE_URL', 'https://qris.interactive.co.id/restapi/qris'),
        'apikey' => env('INTERACTIVE_QRIS_APIKEY'),
        'merchant_id' => env('INTERACTIVE_QRIS_MID'),
        'use_tip' => env('INTERACTIVE_QRIS_USE_TIP', 'no'),
    ],

    'billing_payment' => [
        'manual_transfer' => [
            'bank_name' => env('BILLING_TRANSFER_BANK_NAME', ''),
            'account_name' => env('BILLING_TRANSFER_ACCOUNT_NAME', ''),
            'account_number' => env('BILLING_TRANSFER_ACCOUNT_NUMBER', ''),
            'notes' => env('BILLING_TRANSFER_NOTES', ''),
            'evidence_secret' => env('BILLING_TRANSFER_EVIDENCE_SECRET', ''),
        ],
    ],

];
