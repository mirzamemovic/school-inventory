<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoanCondition;
use App\Http\Controllers\Controller;
use App\Models\BookLoan;
use App\Models\BookTitle;
use App\Models\Person;
use App\Services\BookService;
use App\Services\ClassDebtLinks;
use App\Support\AcademicYear;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function __construct(
        private readonly BookService $books,
        private readonly ClassDebtLinks $links,
    ) {}

    /** Title, subject, grade, copies owned / out / available. */
    public function titles(Request $request): View
    {
        $titles = BookTitle::query()
            ->withCount(['loans as copies_out_count' => fn ($q) => $q->outstanding()])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->orderBy('grade')
            ->orderBy('subject')
            ->orderBy('title')
            ->get();

        return view('admin.books.titles', ['titles' => $titles]);
    }

    public function storeTitle(Request $request): RedirectResponse
    {
        BookTitle::query()->create($this->validatedTitle($request));

        return redirect()->route('admin.books.titles')->with('status', __('books.title_added'));
    }

    public function editTitle(BookTitle $title): View
    {
        return view('admin.books.edit-title', ['title' => $title]);
    }

    public function updateTitle(Request $request, BookTitle $title): RedirectResponse
    {
        $title->update($this->validatedTitle($request));

        return redirect()->route('admin.books.titles')->with('status', __('books.changes_saved'));
    }

    /** September: pick a class, everything for that grade arrives pre-ticked. */
    public function issueForm(Request $request): View
    {
        $class = $request->string('class')->toString() ?: null;
        $students = $this->studentsIn($class);
        $grade = $students->first()?->grade();

        return view('admin.books.issue', [
            'classes' => $this->books->classes(),
            'class' => $class,
            'students' => $students,
            'titles' => $this->books->titlesForGrade($grade),
            'held' => $this->heldTitlesByPerson($students),
            'academicYear' => AcademicYear::current(),
        ]);
    }

    public function issue(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class' => ['required', 'string'],
            'selection' => ['required', 'array'],
            'selection.*' => ['array'],
            'selection.*.*' => ['integer', Rule::exists('book_titles', 'id')],
        ]);

        $issued = $this->books->issue($validated['selection']);

        return redirect()
            ->route('admin.books.issue.form', ['class' => $validated['class']])
            ->with('status', trans_choice('books.issued', $issued, ['count' => $issued]));
    }

    /** June: who still owes what, on paper if the class teacher wants it. */
    public function returnsForm(Request $request): View
    {
        $class = $request->string('class')->toString() ?: null;
        $students = $this->studentsIn($class, ['outstandingLoans.title']);

        return view('admin.books.returns', [
            'classes' => $this->books->classes(),
            'class' => $class,
            'students' => $students,
            'shareUrl' => $class ? $this->links->urlFor($class) : null,
        ]);
    }

    /**
     * The link has gone somewhere it should not. Every copy of it stops working
     * at once, and the Returns screen shows the new one.
     */
    public function replaceClassLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class' => ['required', 'string', Rule::in($this->books->classes()->all())],
        ]);

        $this->links->replace($validated['class'], $request->user());

        return redirect()
            ->route('admin.books.returns.form', ['class' => $validated['class']])
            ->with('status', __('books.share_replaced'));
    }

    public function takeBack(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class' => ['required', 'string'],
            'returned' => ['required', 'array'],
            'returned.*' => [Rule::enum(LoanCondition::class)],
        ]);

        $count = $this->books->takeBack($validated['returned']);

        return redirect()
            ->route('admin.books.returns.form', ['class' => $validated['class']])
            ->with('status', trans_choice('books.returned', $count, ['count' => $count]));
    }

    /** One student: what they hold now, and what they held in past years. */
    public function student(Person $person): View
    {
        $grade = $person->grade();
        $year = AcademicYear::current();

        return view('admin.books.student', [
            'person' => $person,
            'current' => $person->loans()->with('title')->forYear($year)->orderBy('id')->get(),
            'previous' => $person->loans()->with('title')->where('academic_year', '!=', $year)->latest('issued_at')->get(),
            'titles' => $this->books->titlesForGrade($grade),
            'held' => $person->outstandingLoans()->pluck('book_title_id')->all(),
        ]);
    }

    /** Mid-year: tick or untick one title for one student. */
    public function updateStudent(Request $request, Person $person): RedirectResponse
    {
        $validated = $request->validate([
            'titles' => ['array'],
            'titles.*' => ['integer', Rule::exists('book_titles', 'id')],
        ]);

        $wanted = array_map('intval', $validated['titles'] ?? []);
        $held = $person->outstandingLoans()->pluck('book_title_id')->all();

        $this->books->issue([$person->id => array_diff($wanted, $held)]);

        $toReturn = BookLoan::query()
            ->where('person_id', $person->id)
            ->outstanding()
            ->whereIn('book_title_id', array_diff($held, $wanted))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => LoanCondition::Good->value])
            ->all();

        $this->books->takeBack($toReturn);

        return redirect()
            ->route('admin.books.student', $person)
            ->with('status', __('books.changes_saved'));
    }

    /**
     * Nobody has picked a class yet, so there is nothing to show. This still
     * hands back an Eloquent collection rather than a plain one, because the
     * views and the caller expect model-collection behaviour either way.
     *
     * @param  array<int, string>  $with
     * @return EloquentCollection<int, Person>
     */
    private function studentsIn(?string $class, array $with = []): EloquentCollection
    {
        if ($class === null) {
            return new EloquentCollection;
        }

        return Person::query()
            ->students()
            ->active()
            ->inClass($class)
            ->with($with)
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array<int, int>> */
    private function heldTitlesByPerson($students): array
    {
        return BookLoan::query()
            ->outstanding()
            ->whereIn('person_id', $students->pluck('id'))
            ->get()
            ->groupBy('person_id')
            ->map(fn ($loans) => $loans->pluck('book_title_id')->all())
            ->all();
    }

    /** @return array<string, mixed> */
    private function validatedTitle(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'grade' => ['required', 'integer', 'min:1', 'max:13'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'total_copies' => ['required', 'integer', 'min:0', 'max:5000'],
        ]);
    }
}
