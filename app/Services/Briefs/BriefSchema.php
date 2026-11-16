<?php

declare(strict_types=1);

namespace App\Services\Briefs;

/**
 * Движок схем брифов: нормализация, видимость полей, валидация ответов, прогресс.
 *
 * Схема — обычный JSON (секции → поля), поэтому новый бриф добавляется
 * JSON-файлом, без правок кода. Логика повторяет движок inteli-brief,
 * но без Telegram: ответы приходят из веб-мастера CRM.
 */
class BriefSchema
{
    /**
     * Приводит схему к предсказуемому виду.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function normalize(array $schema): array
    {
        $sections = [];

        foreach ((array) ($schema['sections'] ?? []) as $section) {
            if (! is_array($section) || ! isset($section['key'])) {
                continue;
            }

            $fields = [];

            foreach ((array) ($section['fields'] ?? []) as $field) {
                if (! is_array($field) || ! isset($field['key'], $field['type'])) {
                    continue;
                }

                $field['label'] = (string) ($field['label'] ?? $field['key']);
                $field['required'] = (bool) ($field['required'] ?? false);
                $field['options'] = array_values(array_filter((array) ($field['options'] ?? []), 'is_array'));
                $field['validation'] = is_array($field['validation'] ?? null) ? $field['validation'] : [];
                $field['show_if'] = is_array($field['show_if'] ?? null) ? $field['show_if'] : null;
                $fields[] = $field;
            }

            $sections[] = [
                'key' => (string) $section['key'],
                'title' => (string) ($section['title'] ?? $section['key']),
                'description' => (string) ($section['description'] ?? ''),
                'fields' => $fields,
            ];
        }

        return [
            'slug' => (string) ($schema['slug'] ?? ''),
            'title' => (string) ($schema['title'] ?? ''),
            'description' => (string) ($schema['description'] ?? ''),
            'icon' => (string) ($schema['icon'] ?? ''),
            'estimated_minutes' => (int) ($schema['estimated_minutes'] ?? 0),
            'sections' => $sections,
            'submit' => is_array($schema['submit'] ?? null) ? $schema['submit'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function hash(array $schema): string
    {
        return hash('sha256', json_encode(self::normalize($schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    public static function sections(array $schema): array
    {
        return self::normalize($schema)['sections'];
    }

    /**
     * Все поля схемы, включая скрытые.
     *
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    public static function allFields(array $schema): array
    {
        $fields = [];

        foreach (self::sections($schema) as $section) {
            foreach ($section['fields'] as $field) {
                $field['section_key'] = $section['key'];
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    public static function fieldsByKey(array $schema): array
    {
        $map = [];

        foreach (self::allFields($schema) as $field) {
            $map[(string) $field['key']] = $field;
        }

        return $map;
    }

    /**
     * Видимость поля по `show_if` и текущим ответам.
     *
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $answers
     */
    public static function isVisible(?array $condition, array $answers): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $part) {
                if (! self::isVisible(is_array($part) ? $part : null, $answers)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $part) {
                if (self::isVisible(is_array($part) ? $part : null, $answers)) {
                    return true;
                }
            }

            return false;
        }

        $field = (string) ($condition['field'] ?? '');
        $op = (string) ($condition['op'] ?? 'eq');
        $expected = $condition['value'] ?? null;
        $actual = $answers[$field] ?? null;

        return match ($op) {
            'eq' => self::looseEquals($actual, $expected),
            'neq' => ! self::looseEquals($actual, $expected),
            'in' => is_array($expected) && in_array(self::scalar($actual), array_map(self::scalar(...), $expected), true),
            'not_in' => ! (is_array($expected) && in_array(self::scalar($actual), array_map(self::scalar(...), $expected), true)),
            'contains' => is_array($actual) && in_array(self::scalar($expected), array_map(self::scalar(...), $actual), true),
            'answered' => self::isAnswered($actual),
            'not_answered' => ! self::isAnswered($actual),
            'gt' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            default => true,
        };
    }

    /**
     * Видимые поля секции для текущих ответов.
     *
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $answers
     * @return list<array<string, mixed>>
     */
    public static function visibleFields(array $section, array $answers): array
    {
        $fields = [];

        foreach ((array) ($section['fields'] ?? []) as $field) {
            if (! is_array($field) || ! isset($field['key'])) {
                continue;
            }

            if (self::isVisible(is_array($field['show_if'] ?? null) ? $field['show_if'] : null, $answers)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public static function isAnswered(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::isAnswered($item)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Ошибки валидации: {field_key: сообщение}. Скрытые поля не проверяются.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @return array<string, string>
     */
    public static function validate(array $schema, array $answers): array
    {
        $errors = [];

        foreach (self::sections($schema) as $section) {
            foreach (self::visibleFields($section, $answers) as $field) {
                $key = (string) $field['key'];
                $value = $answers[$key] ?? null;

                if (! self::isAnswered($value)) {
                    if ((bool) ($field['required'] ?? false)) {
                        $errors[$key] = 'Обязательное поле: '.((string) ($field['label'] ?? $key));
                    }

                    continue;
                }

                $message = self::validateValue($field, $value);

                if ($message !== null) {
                    $errors[$key] = $message;
                }
            }
        }

        return $errors;
    }

    /**
     * Прогресс заполнения в процентах.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $answers
     */
    public static function progress(array $schema, array $answers): int
    {
        $required = 0;
        $filled = 0;
        $visibleAny = 0;
        $answeredAny = 0;

        foreach (self::sections($schema) as $section) {
            foreach (self::visibleFields($section, $answers) as $field) {
                $visibleAny++;
                $answered = self::isAnswered($answers[(string) $field['key']] ?? null);

                if ($answered) {
                    $answeredAny++;
                }

                if ((bool) ($field['required'] ?? false)) {
                    $required++;
                    if ($answered) {
                        $filled++;
                    }
                }
            }
        }

        if ($required > 0) {
            return (int) round($filled / $required * 100);
        }

        return $visibleAny > 0 ? (int) round($answeredAny / $visibleAny * 100) : 0;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function validateValue(array $field, mixed $value): ?string
    {
        $type = (string) $field['type'];
        $rules = is_array($field['validation'] ?? null) ? $field['validation'] : [];

        return match ($type) {
            'text', 'textarea' => self::validateString($value, $rules),
            'number' => self::validateNumber($value, $rules),
            'date' => self::validateDate($value, $rules),
            'url' => self::validateUrl($value, $rules),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : 'Укажите корректный email',
            'phone' => self::validatePhone($value),
            'single_choice' => self::validateSingleChoice($field, $value),
            'multi_choice' => self::validateMultiChoice($field, $value, $rules),
            'scale' => self::validateNumber($value, $rules),
            'file' => is_string($value) && $value !== '' ? null : 'Прикрепите файл',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private static function validateString(mixed $value, array $rules): ?string
    {
        if (! is_string($value)) {
            return 'Ожидается текст';
        }

        $max = isset($rules['max_length']) ? (int) $rules['max_length'] : null;
        $min = isset($rules['min_length']) ? (int) $rules['min_length'] : null;

        if ($max !== null && mb_strlen($value) > $max) {
            return "Не длиннее {$max} символов";
        }

        if ($min !== null && mb_strlen($value) < $min) {
            return "Не короче {$min} символов";
        }

        $pattern = $rules['pattern'] ?? null;

        if (is_string($pattern) && $pattern !== '' && @preg_match('/'.$pattern.'/u', $value) !== 1) {
            return 'Значение не соответствует формату';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private static function validateNumber(mixed $value, array $rules): ?string
    {
        if (! is_numeric($value)) {
            return 'Введите число';
        }

        $number = (float) $value;

        if (($rules['integer'] ?? false) && (float) (int) $number !== $number) {
            return 'Введите целое число';
        }

        if (isset($rules['min']) && $number < (float) $rules['min']) {
            return 'Минимум '.$rules['min'];
        }

        if (isset($rules['max']) && $number > (float) $rules['max']) {
            return 'Максимум '.$rules['max'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private static function validateDate(mixed $value, array $rules): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return 'Укажите дату';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($date === false) {
            return 'Некорректная дата';
        }

        if (($rules['not_past'] ?? false) && $value < now()->toDateString()) {
            return 'Дата не может быть в прошлом';
        }

        if (isset($rules['min_date']) && $value < (string) $rules['min_date']) {
            return 'Не раньше '.$rules['min_date'];
        }

        if (isset($rules['max_date']) && $value > (string) $rules['max_date']) {
            return 'Не позже '.$rules['max_date'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private static function validateUrl(mixed $value, array $rules): ?string
    {
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return 'Укажите корректный адрес';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $allowed = array_map('strtolower', array_map('strval', (array) ($rules['allowed_schemes'] ?? ['http', 'https'])));

        return in_array($scheme, $allowed, true) ? null : 'Допустимы только ссылки http/https';
    }

    private static function validatePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'Укажите телефон';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 10 ? null : 'Укажите телефон в формате +7 999 123-45-67';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function validateSingleChoice(array $field, mixed $value): ?string
    {
        $values = array_map(
            static fn (array $option): string => (string) ($option['value'] ?? ''),
            (array) ($field['options'] ?? []),
        );

        if (($field['allow_other'] ?? false) === true) {
            $values[] = 'other';
        }

        return in_array((string) $value, $values, true) ? null : 'Выберите вариант из списка';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $rules
     */
    private static function validateMultiChoice(array $field, mixed $value, array $rules): ?string
    {
        if (! is_array($value)) {
            return 'Выберите хотя бы один вариант';
        }

        $allowed = array_map(
            static fn (array $option): string => (string) ($option['value'] ?? ''),
            (array) ($field['options'] ?? []),
        );

        if (($field['allow_other'] ?? false) === true) {
            $allowed[] = 'other';
        }

        foreach ($value as $item) {
            if (! in_array((string) $item, $allowed, true)) {
                return 'Неизвестный вариант ответа';
            }
        }

        if (isset($rules['min_items']) && count($value) < (int) $rules['min_items']) {
            return 'Выберите минимум '.(int) $rules['min_items'];
        }

        if (isset($rules['max_items']) && count($value) > (int) $rules['max_items']) {
            return 'Не больше '.(int) $rules['max_items'];
        }

        return null;
    }

    private static function looseEquals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($expected)) {
            return (bool) $actual === $expected;
        }

        return self::scalar($actual) === self::scalar($expected);
    }

    private static function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return implode(',', array_map(self::scalar(...), $value));
        }

        return (string) ($value ?? '');
    }
}
