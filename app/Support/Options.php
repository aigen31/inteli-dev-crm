<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;

class Options
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public static function taskStatuses(): array
    {
        return array_map(fn (TaskStatus $status) => ['value' => $status->value, 'label' => $status->label()], TaskStatus::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function taskTypes(): array
    {
        return array_map(fn (TaskType $type) => ['value' => $type->value, 'label' => $type->label()], TaskType::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function priorities(): array
    {
        return array_map(fn (TaskPriority $priority) => ['value' => $priority->value, 'label' => $priority->label()], TaskPriority::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function invoiceStatuses(): array
    {
        return array_map(fn (InvoiceStatus $status) => ['value' => $status->value, 'label' => $status->label()], InvoiceStatus::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function paymentMethods(): array
    {
        return array_map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function customFieldEntities(): array
    {
        return CustomFieldEntity::options();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function customFieldTypes(): array
    {
        return CustomFieldType::options();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function taskStatusesForSelect(): array
    {
        return self::taskStatuses();
    }
}
