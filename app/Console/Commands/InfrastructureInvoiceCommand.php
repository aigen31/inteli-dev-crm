<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Infrastructure\InfrastructureBilling;
use Illuminate\Console\Command;

class InfrastructureInvoiceCommand extends Command
{
    protected $signature = 'infrastructure:invoice';

    protected $description = 'Создать счета за инфраструктурные услуги за текущий месяц';

    public function handle(InfrastructureBilling $billing): int
    {
        $result = $billing->runMonthly();

        $this->info("Создано счетов: {$result['created']}");

        return self::SUCCESS;
    }
}
