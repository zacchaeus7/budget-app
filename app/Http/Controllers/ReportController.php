<?php

namespace App\Http\Controllers;

use App\Models\Budgets;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        abort_unless($month >= 1 && $month <= 12, 422, 'Le mois doit etre compris entre 1 et 12.');
        abort_unless($year >= 2000 && $year <= 2100, 422, 'L annee demandee est invalide.');

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $transactionsQuery = Transaction::query()
            // ->where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);

        $totalDepenses = (float) (clone $transactionsQuery)
            ->where('type', 'expense')
            ->sum('amount');

        $totalEntrees = (float) (clone $transactionsQuery)
            ->where('type', 'income')
            ->sum('amount');

        $totalOperations = (clone $transactionsQuery)->count();

        $budgetInitial = (float) Budgets::query()
            // ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $endOfMonth)
            ->whereDate('end_date', '>=', $startOfMonth)
            ->sum('amount');

        $topExpenseCategory = Transaction::query()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            // ->where('transactions.user_id', $user->id)
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->groupBy('transactions.category_id', 'categories.name')
            ->select(
                'transactions.category_id',
                'categories.name',
                DB::raw('SUM(transactions.amount) as total_amount')
            )
            ->orderByDesc('total_amount')
            ->first();

        $soldeFinal = $totalEntrees - $totalDepenses;
        $soldeProjeteCloture = $budgetInitial + $soldeFinal;

        return response()->json([
            'period' => [
                'month' => $month,
                'year' => $year,
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ],
            'data' => [
                'solde_projete_a_la_cloture' => $soldeProjeteCloture,
                'budget_initial' => $budgetInitial,
                'total_depenses' => $totalDepenses,
                'total_entrees' => $totalEntrees,
                'total_operations' => $totalOperations,
                'solde_final' => $soldeFinal,
                'top_expense_category' => $topExpenseCategory ? [
                    'category_id' => $topExpenseCategory->category_id,
                    'name' => $topExpenseCategory->name,
                    'total_amount' => (float) $topExpenseCategory->total_amount,
                ] : null,
            ],
        ]);
    }
}
