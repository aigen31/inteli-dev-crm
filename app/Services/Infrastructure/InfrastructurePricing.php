<?php

declare(strict_types=1);

namespace App\Services\Infrastructure;

/**
 * Тарифы инфраструктурных услуг: резервное копирование и DR-кластеры.
 * Формулы зафиксированы в docs/agents/infrastructure.md.
 */
class InfrastructurePricing
{
    public const BACKUP_BASE = 390.0;

    public const BACKUP_STORAGE_PER_GB = 60.0;

    public const BACKUP_FREQUENCY_STEP = 150.0;

    public const BACKUP_MIN = 590.0;

    public const BACKUP_SETUP = 1500.0;

    /** @var array<string, array<string, float>> */
    public const CLUSTER_PRICES = [
        'cold' => ['S' => 4900.0, 'M' => 7900.0, 'L' => 12900.0],
        'warm' => ['S' => 14900.0, 'M' => 24900.0, 'L' => 39900.0],
        'hot' => ['S' => 34900.0, 'M' => 59900.0, 'L' => 94900.0],
        'active_active' => ['S' => 120000.0, 'M' => 180000.0, 'L' => 250000.0],
    ];

    /** @var array<string, array<string, float>> */
    public const CLUSTER_SETUP = [
        'cold' => ['S' => 25000.0, 'M' => 25000.0, 'L' => 35000.0],
        'warm' => ['S' => 45000.0, 'M' => 60000.0, 'L' => 85000.0],
        'hot' => ['S' => 90000.0, 'M' => 120000.0, 'L' => 170000.0],
        'active_active' => ['S' => 180000.0, 'M' => 180000.0, 'L' => 250000.0],
    ];

    public function backupMonthly(float $quotaGb, int $frequencyPerWeek): float
    {
        $frequency = max(1, min(7, $frequencyPerWeek));
        $storage = self::BACKUP_BASE + self::BACKUP_STORAGE_PER_GB * max(0.0, $quotaGb);
        $price = max(self::BACKUP_MIN, $storage) + self::BACKUP_FREQUENCY_STEP * ($frequency - 1);

        return round($price, 2);
    }

    public function clusterMonthly(string $tier, string $size): float
    {
        $tier = array_key_exists($tier, self::CLUSTER_PRICES) ? $tier : 'warm';
        $size = array_key_exists($size, self::CLUSTER_PRICES[$tier]) ? $size : 'S';

        return self::CLUSTER_PRICES[$tier][$size];
    }

    public function clusterSetup(string $tier, string $size): float
    {
        $tier = array_key_exists($tier, self::CLUSTER_SETUP) ? $tier : 'warm';
        $size = array_key_exists($size, self::CLUSTER_SETUP[$tier]) ? $size : 'S';

        return self::CLUSTER_SETUP[$tier][$size];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function options(): array
    {
        return [
            'clusters' => [
                'cold' => ['label' => 'DR-Cold · 4–8 ч', 'prices' => self::CLUSTER_PRICES['cold'], 'setup' => self::CLUSTER_SETUP['cold']],
                'warm' => ['label' => 'DR-Warm · 30–60 мин', 'prices' => self::CLUSTER_PRICES['warm'], 'setup' => self::CLUSTER_SETUP['warm']],
                'hot' => ['label' => 'DR-Hot · 5–15 мин', 'prices' => self::CLUSTER_PRICES['hot'], 'setup' => self::CLUSTER_SETUP['hot']],
                'active_active' => ['label' => 'Active-Active · ≈ 0', 'prices' => self::CLUSTER_PRICES['active_active'], 'setup' => self::CLUSTER_SETUP['active_active']],
            ],
            'sizes' => ['S', 'M', 'L'],
        ];
    }
}
