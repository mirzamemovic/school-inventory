<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PersonType;
use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    public function index(Request $request): View
    {
        return view('admin.people.index', [
            'people' => Person::query()
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
                ->when($request->filled('class'), fn ($q) => $q->where('class', $request->string('class')))
                ->withCount(['outstandingLoans as books_out'])
                ->orderByDesc('active')
                ->orderBy('name')
                ->paginate(100)
                ->withQueryString(),
            'classes' => Person::query()->students()->whereNotNull('class')->distinct()->orderBy('class')->pluck('class'),
        ]);
    }

    public function create(): View
    {
        return view('admin.people.create', ['person' => new Person(['type' => PersonType::Student, 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $person = Person::query()->create($this->validated($request));

        $this->log->record($person, EventAction::PERSON_ADDED, ['name' => $person->name]);

        return redirect()->route('admin.people.index')->with('status', __('people.added'));
    }

    public function edit(Person $person): View
    {
        return view('admin.people.edit', ['person' => $person]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $person->update($this->validated($request));

        return redirect()->route('admin.people.index')->with('status', __('people.changes_saved'));
    }

    /** Deactivate, never delete: old book loans point at these records. */
    public function deactivate(Person $person): RedirectResponse
    {
        $person->update(['active' => false]);

        $this->log->record($person, EventAction::PERSON_DEACTIVATED, ['name' => $person->name]);

        return back()->with('status', __('people.deactivated', ['name' => $person->name]));
    }

    public function reactivate(Person $person): RedirectResponse
    {
        $person->update(['active' => true]);

        return back()->with('status', __('people.reactivated', ['name' => $person->name]));
    }

    public function importForm(): View
    {
        return view('admin.people.import');
    }

    /**
     * September intake arrives as a spreadsheet every year. Three columns,
     * nothing clever.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $rows = array_map('str_getcsv', file($request->file('csv')->getRealPath()));
        $added = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[0] ?? ''));
            $type = strtolower(trim((string) ($row[1] ?? 'student')));
            $class = trim((string) ($row[2] ?? '')) ?: null;

            // A header row, a blank line, or a type nobody recognises.
            if ($name === '' || strtolower($name) === 'name' || PersonType::tryFrom($type) === null) {
                $skipped++;

                continue;
            }

            if (Person::query()->where('name', $name)->where('class', $class)->exists()) {
                $skipped++;

                continue;
            }

            $person = Person::query()->create([
                'name' => $name,
                'type' => $type,
                'class' => $class,
                'active' => true,
            ]);

            $this->log->record($person, EventAction::PERSON_ADDED, ['name' => $name, 'via' => 'CSV import']);
            $added++;
        }

        return redirect()
            ->route('admin.people.index')
            ->with('status', __('people.imported', ['added' => $added, 'skipped' => $skipped]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PersonType::class)],
            'class' => ['nullable', 'string', 'max:32', 'required_if:type,student'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
    }
}
