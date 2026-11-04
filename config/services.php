<?php

return [

    'wilayah' => [
        'provinces_url' => env('WILAYAH_PROVINCES_URL'),
        'regencies_url' => env('WILAYAH_REGENCIES_URL'),
        'districts_url' => env('WILAYAH_DISTRICTS_URL'),
        'villages_url' => env('WILAYAH_VILLAGES_URL'),
    ],

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
    
    'locationiq' => [
    'key' => env('LOCATIONIQ_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI')
    ],

    'midtrans' => [
        'merchant_id'   => env('MIDTRANS_MERCHANT_ID'),
        'client_key'    => env('MIDTRANS_CLIENT_KEY'),
        'server_key'    => env('MIDTRANS_SERVER_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized'  => env('MIDTRANS_IS_SANITIZED', true),
        'is_3ds'        => env('MIDTRANS_IS_3DS', true),
    ],

    // Midtrans Iris — Disbursement / Payout ke rekening nakes
    'iris' => [
        // API Key Iris (dari dashboard Iris Midtrans)
        'api_key'      => env('IRIS_API_KEY'),

        // Merchant Key Iris (untuk validasi webhook/callback)
        'merchant_key' => env('IRIS_MERCHANT_KEY'),

        // Base URL API Iris
        'base_url'     => env('IRIS_IS_PRODUCTION', false)
            ? 'https://app.iris.midtrans.com/api/v1'
            : 'https://app.sandbox.iris.midtrans.com/api/v1',

        'is_production' => env('IRIS_IS_PRODUCTION', false),
    ],

];
