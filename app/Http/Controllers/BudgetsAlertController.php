<?php

namespace App\Http\Controllers;

use App\Models\Budgets;
use App\Models\BudgetsAlert;
use Illuminate\Http\Request;

class BudgetsAlertController extends Controller
{
    public function index(Request $request)
    {
        $alerts = BudgetsAlert::query()
            ->with(['budget.category'])
            ->whereHas('budget', fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->get();

        return response()->json($alerts);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'budget_id' => ['required', 'integer', 'exists:budgets,id'],
            'threshold_percentage' => ['required', 'integer', 'min:1', 'max:100'],
            'notified' => ['nullable', 'boolean'],
        ]);

        $budget = Budgets::where('user_id', $request->user()->id)->findOrFail($data['budget_id']);

        $alert = $budget->alerts()->create([
            'threshold_percentage' => $data['threshold_percentage'],
            'notified' => $data['notified'] ?? false,
        ]);

        return response()->json($alert->load('budget.category'), 201);
    }

    public function show(Request $request, BudgetsAlert $budgetsAlert)
    {
        $budgetsAlert->load('budget.category');
        abort_unless($budgetsAlert->budget->user_id === $request->user()->id, 404);

        return response()->json($budgetsAlert);
    }

    public function update(Request $request, BudgetsAlert $budgetsAlert)
    {
        $budgetsAlert->load('budget');
        abort_unless($budgetsAlert->budget->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'threshold_percentage' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'notified' => ['sometimes', 'required', 'boolean'],
        ]);

        $budgetsAlert->update($data);

        return response()->json($budgetsAlert->fresh()->load('budget.category'));
    }

    public function destroy(Request $request, BudgetsAlert $budgetsAlert)
    {
        $budgetsAlert->load('budget');
        abort_unless($budgetsAlert->budget->user_id === $request->user()->id, 404);

        $budgetsAlert->delete();

        return response()->json(['message' => 'Alerte budget supprimee avec succes.']);
    }
}
