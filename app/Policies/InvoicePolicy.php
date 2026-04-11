<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $invoice->client_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->isPending();
    }

    public function refund(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->isPaid();
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $invoice->client_id === $user->id && $invoice->isPending();
    }
}
