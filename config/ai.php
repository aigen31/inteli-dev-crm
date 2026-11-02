<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_ASSISTANT_ENABLED', true),
    'timeout' => (int) env('AI_TIMEOUT', 90),

    // Central Kiki assistant kernel. The browser never sees these tokens.
    'kernel_url' => rtrim((string) env('KERNEL_URL', ''), '/'),
    'kernel_token' => (string) env('KERNEL_TOKEN', ''),
    'kernel_provider_token' => (string) env('KERNEL_PROVIDER_TOKEN', ''),
    'kernel_channel' => (string) env('KERNEL_CHANNEL', 'crm'),
    'kernel_data_class' => (string) env('KERNEL_DATA_CLASS', 'internal'),
    'kernel_timeout' => (int) env('KERNEL_TIMEOUT', 90),
];
