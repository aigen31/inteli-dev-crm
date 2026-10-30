<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    /** @var list<string> */
    public const TRANSFER_KEYS = [
        'transfer_recipient',
        'transfer_inn',
        'transfer_bank',
        'transfer_account',
        'transfer_bik',
        'transfer_corr_account',
        'transfer_phone',
        'transfer_note',
    ];

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->values ??= Setting::query()->pluck('value', 'key')->all();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || trim($value) === '' ? $default : $value;
    }

    public function put(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->values[$key] = $value;
    }

    /**
     * @return array<string, string|null>
     */
    public function transferFields(): array
    {
        $fields = [];

        foreach (self::TRANSFER_KEYS as $key) {
            $fields[$key] = $this->get($key);
        }

        return $fields;
    }

    /**
     * @return array{items: list<array{label: string, value: string}>, note: string|null, configured: bool}
     */
    public function transferDetails(): array
    {
        $labels = [
            'transfer_recipient' => 'Получатель',
            'transfer_inn' => 'ИНН',
            'transfer_bank' => 'Банк',
            'transfer_account' => 'Счёт получателя',
            'transfer_bik' => 'БИК',
            'transfer_corr_account' => 'Корр. счёт',
            'transfer_phone' => 'Телефон для СБП',
        ];

        $items = [];

        foreach ($labels as $key => $label) {
            $value = $this->get($key);

            if ($value !== null) {
                $items[] = ['label' => $label, 'value' => $value];
            }
        }

        return [
            'items' => $items,
            'note' => $this->get('transfer_note'),
            'configured' => $this->hasTransferDetails(),
        ];
    }

    public function hasTransferDetails(): bool
    {
        foreach (['transfer_recipient', 'transfer_account', 'transfer_phone'] as $key) {
            if ($this->get($key) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function saveTransfer(array $data): void
    {
        foreach (self::TRANSFER_KEYS as $key) {
            $value = $data[$key] ?? null;
            $this->put($key, $value !== null && trim((string) $value) !== '' ? trim((string) $value) : null);
        }
    }
}
