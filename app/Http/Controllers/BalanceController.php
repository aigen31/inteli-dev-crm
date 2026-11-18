<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BalanceTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Баланс обычного пользователя: только свои операции, без управления.
 */
class BalanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $transactions = BalanceTransaction::query()
            ->where('user_id', $user->id)
            ->with(['task:id,title', 'invoice:id,amount,status', 'author:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BalanceTransaction $transaction): array => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'balance_after' => (float) $transaction->balance_after,
                'reason' => $transaction->reason,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at?->toIso8601String(),
                'task' => $transaction->task?->only('id', 'title'),
                'invoice' => $transaction->invoice ? [
                    'id' => $transaction->invoice->id,
                    'amount' => (float) $transaction->invoice->amount,
                    'status' => $transaction->invoice->status?->value,
                ] : null,
                'author' => $transaction->author?->name,
            ]);

        $monthStart = now()->startOfMonth();

        return Inertia::render('Balance/Index', [
            'balance' => (float) $user->balance,
            'transactions' => $transactions,
            'summary' => [
                'charged' => (float) BalanceTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('amount', '<', 0)
                    ->where('created_at', '>=', $monthStart)
                    ->sum('amount'),
                'topped_up' => (float) BalanceTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('amount', '>', 0)
                    ->where('created_at', '>=', $monthStart)
                    ->sum('amount'),
            ],
        ]);
    }
}
