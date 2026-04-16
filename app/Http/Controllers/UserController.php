<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->load(['accounts', 'categories', 'budgets', 'notifications'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        return response()->json($user, 201);
    }

    public function show(Request $request, User $user)
    {
        abort_unless($user->id === $request->user()->id, 404);

        return response()->json(
            $user->load(['accounts', 'categories', 'budgets', 'notifications'])
        );
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($user->id === $request->user()->id, 404);

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprime avec succes.']);
    }
}
