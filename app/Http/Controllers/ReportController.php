<?php

namespace App\Http\Controllers;

use App\Models\Budgets;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
            ->where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);

        $totalDepenses = (float) (clone $transactionsQuery)
            ->where('type', 'expense')
            ->sum('amount');

        $totalEntrees = (float) (clone $transactionsQuery)
            ->where('type', 'income')
            ->sum('amount');

        $budgetInitial = (float) Budgets::query()
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $endOfMonth)
            ->whereDate('end_date', '>=', $startOfMonth)
            ->sum('amount');

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
                'solde_final' => $soldeFinal,
            ],
        ]);
    }
}
