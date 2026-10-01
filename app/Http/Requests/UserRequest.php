<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * A name and an email address: the only things about an account an admin can
 * type. No password (the person chooses their own with a once-only link), no
 * role (there are none), no status (that is Deactivate and Reactivate).
 * Anything else in the request is ignored, because only these two fields are
 * ever read and they are the only fillable ones on User.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Who may act is the route's business (signed in, password re-entered)
        // and the policy's, which the controller asks.
        return true;
    }

    /**
     * One address, one account. Lower-cased so "Drita@School.example" cannot
     * become a second account beside "drita@school.example".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $current = $this->route('user');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($current) {
                    $taken = User::query()
                        ->whereRaw('lower(email) = ?', [$value])
                        ->when($current instanceof User, fn ($query) => $query->whereKeyNot($current->id))
                        ->exists();

                    if ($taken) {
                        $fail(__('users.email_taken'));
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('users.labels.name'),
            'email' => __('users.labels.email'),
        ];
    }

    /** @return array{name: string, email: string} */
    public function details(): array
    {
        return $this->safe()->only(['name', 'email']);
    }
}
