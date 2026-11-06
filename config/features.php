<?php

declare(strict_types=1);

return [
    /*
    | Secret, opt-in feature codes. Codes are intentionally kept out of the
    | frontend bundle; only the server compares them and stores a flag.
    */
    'secret_codes' => [
        'xp_theme' => (string) env('XP_UNLOCK_CODE', 'LunaXP2001'),
    ],
];
