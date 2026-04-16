<?php

namespace App\Http\Controllers;

use App\Models\Budgets;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;

class BudgetsController extends Controller
{
    public function index(Request $request)
    {
        $budgets = Budgets::query()
            ->with(['category', 'alerts'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn (Budgets $budget) => $this->formatBudget($budget));

        return response()->json($budgets);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $category = Category::where('user_id', $request->user()->id)
            ->where('type', 'expense')
            ->findOrFail($data['category_id']);

        $budget = Budgets::create([
            ...$data,
            'user_id' => $request->user()->id,
            'category_id' => $category->id,
        ]);

        return response()->json($this->formatBudget($budget->load(['category', 'alerts'])), 201);
    }

    public function show(Request $request, Budgets $budget)
    {
        abort_unless($budget->user_id === $request->user()->id, 404);

        return response()->json(
            $this->formatBudget($budget->load(['category', 'alerts']))
        );
    }

    public function update(Request $request, Budgets $budget)
    {
        abort_unless($budget->user_id === $request->user()->id, 404);

        $data = $this->validatedData($request);

        Category::where('user_id', $request->user()->id)
            ->where('type', 'expense')
            ->findOrFail($data['category_id']);

        $budget->update($data);

        return response()->json(
            $this->formatBudget($budget->fresh()->load(['category', 'alerts']))
        );
    }

    public function destroy(Request $request, Budgets $budget)
    {
        abort_unless($budget->user_id === $request->user()->id, 404);

        $budget->delete();

        return response()->json(['message' => 'Budget supprime avec succes.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
    }

    private function formatBudget(Budgets $budget): array
    {
        $spent = Transaction::query()
            ->where('user_id', $budget->user_id)
            ->where('category_id', $budget->category_id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [
                $budget->start_date->startOfDay(),
                $budget->end_date->endOfDay(),
            ])
            ->sum('amount');

        $amount = (float) $budget->amount;
        $spent = (float) $spent;
        $remaining = $amount - $spent;
        $percentage = $amount > 0 ? round(($spent / $amount) * 100, 2) : 0;

        return [
            'id' => $budget->id,
            'user_id' => $budget->user_id,
            'category_id' => $budget->category_id,
            'amount' => number_format($amount, 2, '.', ''),
            'start_date' => $budget->start_date?->toDateString(),
            'end_date' => $budget->end_date?->toDateString(),
            'created_at' => $budget->created_at,
            'updated_at' => $budget->updated_at,
            'category' => $budget->category,
            'alerts' => $budget->alerts,
            'spent' => number_format($spent, 2, '.', ''),
            'remaining' => number_format($remaining, 2, '.', ''),
            'percentage_used' => $percentage,
        ];
    }
}
