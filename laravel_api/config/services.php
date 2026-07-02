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

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'project_id' => env('FCM_PROJECT_ID'),
        'client_email' => env('FCM_CLIENT_EMAIL'),
        'private_key' => env('FCM_PRIVATE_KEY'),
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', env('FCM_PROJECT_ID')),
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'firebase'),
    ],

    'beem' => [
        'sms_url' => env('BEEM_SMS_URL', 'https://apisms.beem.africa/v1/send'),
        'sender' => env('BEEM_SENDER', 'INFO'),
        'access_key' => env('BEEM_ACCESS_KEY'),
        'secret_key' => env('BEEM_SECRET_KEY'),
    ],

    'infobip' => [
        'base_url' => env('INFOBIP_BASE_URL'),
        'api_key' => env('INFOBIP_API_KEY'),
        'sender' => env('INFOBIP_SENDER', 'InfoSMS'),
        'otp_ttl_minutes' => (int) env('INFOBIP_OTP_TTL_MINUTES', 10),
        'otp_message' => env('INFOBIP_OTP_MESSAGE', 'Your Nearest Technician verification code is :code'),
    ],

    'clickpesa' => [
        'base_url' => env('CLICKPESA_BASE_URL', 'https://api.clickpesa.com/third-parties'),
        'client_id' => env('CLICKPESA_CLIENT_ID'),
        'client_id_encrypted' => env('CLICKPESA_CLIENT_ID_ENCRYPTED'),
        'api_key' => env('CLICKPESA_API_KEY'),
        'api_key_encrypted' => env('CLICKPESA_API_KEY_ENCRYPTED'),
        'currency' => env('CLICKPESA_CURRENCY', 'TZS'),
        'technician_registration_fee' => (int) env('CLICKPESA_TECHNICIAN_REGISTRATION_FEE', 5000),
    ],

];
