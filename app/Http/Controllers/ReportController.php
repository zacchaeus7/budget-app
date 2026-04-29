<?php

namespace App\Http\Controllers;

use App\Models\Budgets;
use App\Models\Message;
use App\Models\Notification;
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

        $consommationsUtilisateurs = Transaction::query()
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->groupBy('transactions.user_id', 'users.name', 'users.email')
            ->select(
                'transactions.user_id',
                'users.name',
                'users.email',
                DB::raw('SUM(transactions.amount) as total_consumed')
            )
            ->orderByDesc('total_consumed')
            ->get()
            ->map(fn ($consommation) => [
                'user_id' => $consommation->user_id,
                'name' => $consommation->name,
                'email' => $consommation->email,
                'total_consumed' => (float) $consommation->total_consumed,
            ]);

        $utilisateurPlusGrandConsommateur = $consommationsUtilisateurs->first();
        $targetUserId = $request->filled('user_id')
            ? $request->integer('user_id')
            : ($user?->role === 'admin'
                ? $utilisateurPlusGrandConsommateur['user_id'] ?? null
                : $user?->id);

        $utilisateurTotalConsomme = $targetUserId
            ? $consommationsUtilisateurs->firstWhere('user_id', $targetUserId)
            : null;

        $totalConsommeUtilisateur = (float) ($utilisateurTotalConsomme['total_consumed'] ?? 0);

        $budgetInitial = (float) Budgets::query()
            // ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $endOfMonth)
            ->whereDate('end_date', '>=', $startOfMonth)
            ->sum('amount');

        $messagesBudget = collect();

        if ($user && $user->role === 'admin' && $budgetInitial > 0) {
            $messagesBudget = $consommationsUtilisateurs
                ->map(function ($consommation) use ($user, $month, $year, $budgetInitial) {
                    $consumedPercentage = round(($consommation['total_consumed'] / $budgetInitial) * 100, 2);

                    if ($consumedPercentage <= 25) {
                        return null;
                    }

                    $message = Message::updateOrCreate(
                        [
                            'admin_id' => $user->id,
                            'user_id' => $consommation['user_id'],
                            'month' => $month,
                            'year' => $year,
                        ],
                        [
                            'monthly_budget_amount' => $budgetInitial,
                            'consumed_amount' => $consommation['total_consumed'],
                            'consumed_percentage' => $consumedPercentage,
                            'message' => 'Vous avez consomme plus de 25% du budget mensuel.',
                        ]
                    );

                    Notification::firstOrCreate(
                        [
                            'user_id' => $consommation['user_id'],
                            'type' => 'budget_alert',
                            'scheduled_slot' => sprintf('%04d-%02d', $year, $month),
                            'message' => $message->message,
                        ],
                        [
                            'is_sent' => false,
                        ]
                    );

                    return $message;
                })
                ->filter()
                ->values()
                ->map(fn (Message $message) => [
                    'id' => $message->id,
                    'admin_id' => $message->admin_id,
                    'user_id' => $message->user_id,
                    'month' => $message->month,
                    'year' => $message->year,
                    'monthly_budget_amount' => (float) $message->monthly_budget_amount,
                    'consumed_amount' => (float) $message->consumed_amount,
                    'consumed_percentage' => (float) $message->consumed_percentage,
                    'message' => $message->message,
                    'sent_at' => $message->sent_at,
                    'read_at' => $message->read_at,
                ]);
        }

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
                'total_consomme_utilisateur' => $totalConsommeUtilisateur,
                'utilisateur_total_consomme' => $utilisateurTotalConsomme,
                'consommations_utilisateurs' => $consommationsUtilisateurs,
                'utilisateur_plus_grand_consommateur' => $utilisateurPlusGrandConsommateur,
                'messages_budget' => $messagesBudget,
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
