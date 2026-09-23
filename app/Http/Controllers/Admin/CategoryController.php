<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('assets')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::query()->create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('status', __('categories.added'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        // The prefix is frozen once any tag has been printed with it: changing
        // it would make old stickers disagree with new ones.
        if ($category->assets()->exists()) {
            unset($data['prefix']);
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', __('categories.changes_saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category)],
            'prefix' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9]+$/'],
            'needs_inspection_default' => ['boolean'],
            'default_inspection_interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
    }
}
