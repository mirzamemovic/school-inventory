<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()->withCount('assets')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $category = Category::query()->create($validated);

            $this->log->record($category, EventAction::CATEGORY_CREATED, [
                'name' => $category->name,
                'prefix' => $category->prefix,
            ]);
        });

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

        DB::transaction(function () use ($category, $data) {
            $before = $this->snapshot($category);
            $category->update($data);

            $this->log->recordChanges($category, EventAction::CATEGORY_EDITED, $before, $this->snapshot($category), $category->name);
        });

        return redirect()->route('admin.categories.index')->with('status', __('categories.changes_saved'));
    }

    /** @return array<string, mixed> */
    private function snapshot(Category $category): array
    {
        return [
            'name' => $category->name,
            'prefix' => $category->prefix,
            'needs_inspection_default' => $category->needs_inspection_default,
            'default_inspection_interval_months' => $category->default_inspection_interval_months,
        ];
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
