<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_ASSISTANT_ENABLED', true),
    'timeout' => (int) env('AI_TIMEOUT', 90),
];
