<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.category.index', ['categories' => Category::latest()->paginate(10)]);
    }

    public function create(): View
    {
        return view('admin.category.form', ['category' => new Category()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        Category::create($data);

        return redirect()->route('categoria.index')->with('success', 'Categoria creada correctamente.');
    }

    public function show(Category $categorium): RedirectResponse
    {
        return redirect()->route('categoria.edit', $categorium);
    }

    public function edit(Category $categorium): View
    {
        return view('admin.category.form', ['category' => $categorium]);
    }

    public function update(Request $request, Category $categorium): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $categorium->update($data);

        return redirect()->route('categoria.index')->with('success', 'Categoria actualizada correctamente.');
    }

    public function destroy(Category $categorium): RedirectResponse
    {
        $categorium->update(['is_active' => ! $categorium->is_active]);

        return back()->with('success', 'Estado de la categoria actualizado.');
    }
}
