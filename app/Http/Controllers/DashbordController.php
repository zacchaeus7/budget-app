<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budgets;
use App\Models\Notification;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashbordController extends Controller
{
    public function monthly(Request $request)
    {
        $user = $request->user();
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        abort_unless($month >= 1 && $month <= 12, 422, 'Le mois doit etre compris entre 1 et 12.');
        abort_unless($year >= 2000 && $year <= 2100, 422, 'L annee demandee est invalide.');

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $monthlyTransactions = Transaction::query()
            ->with(['account:id,name', 'category:id,name,type'])
            // ->where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);

        $totalIncome = (float) (clone $monthlyTransactions)
            ->where('type', 'income')
            ->sum('amount');

        $totalExpense = (float) (clone $monthlyTransactions)
            ->where('type', 'expense')
            ->sum('amount');

        $totalOperations = (clone $monthlyTransactions)->count();

        $initialBudget = (float) Budgets::query()
            // ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $endOfMonth)
            ->whereDate('end_date', '>=', $startOfMonth)
            ->sum('amount');

        $currentBalance = (float) Account::query()
            // ->where('user_id', $user->id)YUJM
            ->sum('balance');

        $usageRate = $initialBudget > 0
            ? round(($totalExpense / $initialBudget) * 100, 2)
            : 0.0;

        $recentActivities = (clone $monthlyTransactions)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'transaction_date' => $transaction->transaction_date?->toDateTimeString(),
                'account' => $transaction->account ? [
                    'id' => $transaction->account->id,
                    'name' => $transaction->account->name,
                ] : null,
                'category' => $transaction->category ? [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                    'type' => $transaction->category->type,
                ] : null,
            ]);

        $unreadNotificationsCount = ($user->role === 'admin'
            ? Notification::query()
            : $user->notifications())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'period' => [
                'month' => $month,
                'year' => $year,
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ],
            'data' => [
                'current_balance' => $currentBalance,
                'initial_budget' => $initialBudget,
                'usage_rate' => $usageRate,
                'total_expense' => $totalExpense,
                'total_income' => $totalIncome,
                'total_operations' => $totalOperations,
                'unread_notifications_count' => $unreadNotificationsCount,
                'recent_activities' => $recentActivities,
            ],
        ]);
    }
}
