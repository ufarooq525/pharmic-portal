<?php

// Company details printed on invoices and payslips. Fill these in .env once the client provides them.
return [
    'company' => env('COMPANY_NAME', 'Pharmic LH Pharmaceuticals'),
    'address' => env('COMPANY_ADDRESS'),
    'phone' => env('COMPANY_PHONE'),
    'ntn' => env('COMPANY_NTN'),
    'strn' => env('COMPANY_STRN'),

    // Full path to poppler's pdftotext, e.g. C:/laragon/www/pharmic-portal/tools/poppler/pdftotext.exe
    'pdftotext' => env('PDFTOTEXT_PATH') ?: null,
];
