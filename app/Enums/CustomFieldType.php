<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Currency = 'currency';
    case Date = 'date';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Checkbox = 'checkbox';
    case Url = 'url';
    case Email = 'email';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Строка',
            self::Textarea => 'Текст',
            self::Number => 'Число',
            self::Currency => 'Сумма',
            self::Date => 'Дата',
            self::Select => 'Выбор одного',
            self::Multiselect => 'Выбор нескольких',
            self::Checkbox => 'Флажок',
            self::Url => 'Ссылка',
            self::Email => 'Email',
            self::Phone => 'Телефон',
        };
    }

    public function valueColumn(): string
    {
        return match ($this) {
            self::Number, self::Currency => 'value_number',
            self::Date => 'value_date',
            self::Checkbox => 'value_boolean',
            self::Multiselect => 'value_json',
            default => 'value_text',
        };
    }

    public function isMultiValue(): bool
    {
        return $this === self::Multiselect;
    }

    public function isNumeric(): bool
    {
        return in_array($this, [self::Number, self::Currency], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
