<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->categories()->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:income,expense'],
            'description' => ['nullable', 'string'],
        ]);

        $category = $request->user()->categories()->create($data);

        return response()->json($category, 201);
    }

    public function show(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        return response()->json(
            $category->load(['transactions', 'budgets'])
        );
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:income,expense'],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($data);

        return response()->json($category->fresh());
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless($category->user_id === $request->user()->id, 404);

        $category->delete();

        return response()->json(['message' => 'Categorie supprimee avec succes.']);
    }
}
