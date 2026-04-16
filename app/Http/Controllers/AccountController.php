<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->accounts()->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,mobile_money,bank,other'],
            'balance' => ['nullable', 'numeric'],
        ]);

        $account = $request->user()->accounts()->create([
            'name' => $data['name'],
            'type' => $data['type'],
            'balance' => $data['balance'] ?? 0,
        ]);

        return response()->json($account, 201);
    }

    public function show(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        return response()->json(
            $account->load('transactions')
        );
    }

    public function update(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:cash,mobile_money,bank,other'],
            'balance' => ['sometimes', 'required', 'numeric'],
        ]);

        $account->update($data);

        return response()->json($account->fresh());
    }

    public function destroy(Request $request, Account $account)
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        $account->delete();

        return response()->json(['message' => 'Compte supprime avec succes.']);
    }
}
