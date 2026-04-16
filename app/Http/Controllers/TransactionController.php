<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budgets;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        abort_unless($month >= 1 && $month <= 12, 422, 'Le mois doit etre compris entre 1 et 12.');
        abort_unless($year >= 2000 && $year <= 2100, 422, 'L annee demandee est invalide.');

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $monthlyTransactionsQuery = Transaction::query()
            ->with(['account:id,name', 'category:id,name,type'])
            ->where('user_id', $user->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);

        $transactions = (clone $monthlyTransactionsQuery)
            ->orderByDesc('transaction_date')
            ->get();

        $summary = (clone $monthlyTransactionsQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as total_transactions
            ")
            ->first();

        $expensesByCategory = Transaction::query()
            ->select('transactions.category_id', 'categories.name')
            ->selectRaw('SUM(transactions.amount) as total')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc('total')
            ->get();

        $incomeByCategory = Transaction::query()
            ->select('transactions.category_id', 'categories.name')
            ->selectRaw('SUM(transactions.amount) as total')
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.type', 'income')
            ->whereBetween('transactions.transaction_date', [$startOfMonth, $endOfMonth])
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc('total')
            ->get();

        $accountBalances = Account::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'balance']);

        $totalIncome = (float) $summary->total_income;
        $totalExpense = (float) $summary->total_expense;

        return response()->json([
            'period' => [
                'month' => $month,
                'year' => $year,
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ],
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_flow' => $totalIncome - $totalExpense,
                'total_transactions' => (int) $summary->total_transactions,
                'accounts_balance' => (float) $accountBalances->sum(fn (Account $account) => (float) $account->balance),
            ],
            'movements' => $transactions,
            'expenses_by_category' => $expensesByCategory->map(fn ($item) => [
                'category_id' => $item->category_id,
                'category_name' => $item->name,
                'total' => (float) $item->total,
            ]),
            'income_by_category' => $incomeByCategory->map(fn ($item) => [
                'category_id' => $item->category_id,
                'category_name' => $item->name,
                'total' => (float) $item->total,
            ]),
            'accounts' => $accountBalances->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => (float) $account->balance,
            ]),
        ]);
    }

    public function monthlyActivities(Request $request)
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        abort_unless($month >= 1 && $month <= 12, 422, 'Le mois doit etre compris entre 1 et 12.');
        abort_unless($year >= 2000 && $year <= 2100, 422, 'L annee demandee est invalide.');

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $monthlyTransactionsQuery = Transaction::query()
            ->with([
                'account:id,name',
                'category:id,name,type',
                'user:id,name,email',
            ])
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth]);

        $transactions = (clone $monthlyTransactionsQuery)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        $summary = (clone $monthlyTransactionsQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
                COUNT(*) as total_transactions,
                COUNT(DISTINCT user_id) as total_users
            ")
            ->first();

        return response()->json([
            'period' => [
                'month' => $month,
                'year' => $year,
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ],
            'summary' => [
                'total_income' => (float) $summary->total_income,
                'total_expense' => (float) $summary->total_expense,
                'net_flow' => (float) $summary->total_income - (float) $summary->total_expense,
                'total_transactions' => (int) $summary->total_transactions,
                'total_users' => (int) $summary->total_users,
            ],
            'activities' => $transactions,
        ]);
    }

    public function index(Request $request)
    {
        $query = Transaction::query()
            ->with(['account', 'category'])
            // ->where('user_id', $request->user()->id)
            ->latest('transaction_date');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date('date_to'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $user = $request->user();

        $account = $this->findOwnedAccount($user->id, $data['account_id']);
        $category = $this->findOwnedCategory($user->id, $data['category_id']);
        // $this->ensureCategoryMatchesType($category, $data['type']);

        $transaction = DB::transaction(function () use ($data, $user, $account) {
            $transaction = Transaction::create([
                ...$data,
                'user_id' => $user->id,
            ]);

            $this->applyAccountImpact($account, $transaction->type, (float) $transaction->amount);
            $this->syncBudgetAlertsForCategoryAndDate($transaction->user_id, $transaction->category_id, $transaction->transaction_date);

            return $transaction;
        });

        return response()->json($transaction->load(['account', 'category']), 201);
    }

    public function show(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);

        return response()->json(
            $transaction->load(['account', 'category'])
        );
    }

    public function update(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);

        $data = $this->validatedData($request);
        $user = $request->user();

        $oldAccount = $transaction->account;
        $oldCategoryId = $transaction->category_id;
        $oldDate = $transaction->transaction_date;

        $newAccount = $this->findOwnedAccount($user->id, $data['account_id']);
        $newCategory = $this->findOwnedCategory($user->id, $data['category_id']);
        $this->ensureCategoryMatchesType($newCategory, $data['type']);

        DB::transaction(function () use ($transaction, $data, $oldAccount, $newAccount) {
            $this->revertAccountImpact($oldAccount, $transaction->type, (float) $transaction->amount);

            $transaction->update($data);

            $this->applyAccountImpact($newAccount, $transaction->type, (float) $transaction->amount);
            $this->syncBudgetAlertsForCategoryAndDate($transaction->user_id, $transaction->category_id, $transaction->transaction_date);
        });

        $this->syncBudgetAlertsForCategoryAndDate($transaction->user_id, $oldCategoryId, $oldDate);

        return response()->json($transaction->fresh()->load(['account', 'category']));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);

        $categoryId = $transaction->category_id;
        $transactionDate = $transaction->transaction_date;

        DB::transaction(function () use ($transaction) {
            $this->revertAccountImpact($transaction->account, $transaction->type, (float) $transaction->amount);
            $transaction->delete();
        });

        $this->syncBudgetAlertsForCategoryAndDate($request->user()->id, $categoryId, $transactionDate);

        return response()->json(['message' => 'Transaction supprimee avec succes.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
        ]);
    }

    private function findOwnedAccount(int $userId, int $accountId): Account
    {
        return Account::where('user_id', $userId)->findOrFail($accountId);
    }

    private function findOwnedCategory(int $userId, int $categoryId): Category
    {
        return Category::where('user_id', $userId)->findOrFail($categoryId);
    }

    private function ensureCategoryMatchesType(Category $category, string $type): void
    {
        abort_unless(
            $category->type === $type,
            422,
            'Le type de la categorie doit correspondre a celui de la transaction.'
        );
    }

    private function applyAccountImpact(Account $account, string $type, float $amount): void
    {
        $signedAmount = $type === 'income' ? $amount : -$amount;
        $account->increment('balance', $signedAmount);
    }

    private function revertAccountImpact(Account $account, string $type, float $amount): void
    {
        $signedAmount = $type === 'income' ? -$amount : $amount;
        $account->increment('balance', $signedAmount);
    }

    private function syncBudgetAlertsForCategoryAndDate(int $userId, int $categoryId, $transactionDate): void
    {
        $budgets = Budgets::query()
            ->with(['alerts', 'category'])
            ->where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->whereDate('start_date', '<=', $transactionDate)
            ->whereDate('end_date', '>=', $transactionDate)
            ->get();

        foreach ($budgets as $budget) {
            $spent = Transaction::query()
                ->where('user_id', $budget->user_id)
                ->where('category_id', $budget->category_id)
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [
                    $budget->start_date->startOfDay(),
                    $budget->end_date->endOfDay(),
                ])
                ->sum('amount');

            $percentage = $budget->amount > 0 ? ($spent / (float) $budget->amount) * 100 : 0;

            foreach ($budget->alerts as $alert) {
                if ($percentage >= $alert->threshold_percentage && ! $alert->notified) {
                    Notification::create([
                        'user_id' => $budget->user_id,
                        'type' => 'email',
                        'message' => "Le budget de la categorie {$budget->category->name} a atteint {$alert->threshold_percentage}%.",
                        'is_sent' => false,
                    ]);

                    $alert->update(['notified' => true]);
                    continue;
                }

                if ($percentage < $alert->threshold_percentage && $alert->notified) {
                    $alert->update(['notified' => false]);
                }
            }
        }
    }
}
