<?php

declare(strict_types=1);

return [
    /*
     * Shared secret for the CRM MCP endpoint. DeepSeek Harness and other
     * agents authenticate with `Authorization: Bearer <token>`.
     */
    'token' => env('MCP_TOKEN'),

    /*
     * Reports created by an MCP agent are attributed to this user. Empty means
     * the oldest active administrator.
     */
    'actor_id' => env('MCP_ACTOR_ID'),
];
