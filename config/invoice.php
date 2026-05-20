<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company details (shown on PDF invoices / receipts)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('INVOICE_COMPANY_NAME', env('APP_NAME', 'Events Map')),
        'address' => env('INVOICE_COMPANY_ADDRESS', ''),
        'vat_number' => env('INVOICE_COMPANY_VAT_NUMBER', ''),
        'support_email' => env('INVOICE_COMPANY_EMAIL', env('MAIL_FROM_ADDRESS', 'support@example.com')),
        /** Relative to public/ (e.g. images/marker.png) or full https URL */
        'logo_path' => env('INVOICE_LOGO_PATH', env('MAIL_LOGO_URL', 'images/marker.png')),
    ],

    'number_prefix' => env('INVOICE_NUMBER_PREFIX', 'INV'),

    /** Default VAT rate (percent) when Stripe does not provide tax breakdown */
    'default_tax_rate' => (float) env('INVOICE_DEFAULT_TAX_RATE', 0),

    'storage_disk' => env('INVOICE_STORAGE_DISK', 'public'),

    'storage_directory' => 'invoices',

    'queue' => env('INVOICE_EMAIL_QUEUE', 'emails'),

];
