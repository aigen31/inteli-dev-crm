<?php

declare(strict_types=1);

return [
    'manifest_path' => env('INFRA_MANIFEST_PATH') ?: storage_path('app/infrastructure/manifest.json'),
    'invoice_day' => (int) env('INFRA_INVOICE_DAY', 1),
    'report_token' => env('INFRA_REPORT_TOKEN'),
];
