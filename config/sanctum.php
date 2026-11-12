<?php

// Only what differs from Sanctum's defaults (the package fills in the rest).
return [
    // API tokens expire after a week (minutes); sign in again to get a new one.
    'expiration' => (int) env('SANCTUM_EXPIRATION', 60 * 24 * 7),
];
