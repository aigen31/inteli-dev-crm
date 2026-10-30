<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomField;

final readonly class CustomFieldValueResult
{
    public function __construct(
        public CustomField $field,
        public mixed $value,
        public mixed $display,
    ) {}
}
