<?php

// Company details printed on invoices and payslips. Fill these in .env once the client provides them.
return [
    'company' => env('COMPANY_NAME', 'Pharmic LH Pharmaceuticals'),
    'address' => env('COMPANY_ADDRESS'),
    'phone' => env('COMPANY_PHONE'),
    'ntn' => env('COMPANY_NTN'),
    'strn' => env('COMPANY_STRN'),
];
