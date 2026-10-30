<?php

declare(strict_types=1);

namespace App\Services\Ai;

use RuntimeException;

class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 503,
    ) {
        parent::__construct($message);
    }
}
