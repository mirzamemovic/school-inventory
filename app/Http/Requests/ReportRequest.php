<?php

namespace App\Http\Requests;

use App\Enums\ReporterType;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The only unauthenticated write in the application. Nothing here may be widened
 * without thinking about a bored sixteen-year-old first.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reporter_name' => ['required', 'string', 'min:2', 'max:60'],
            'reporter_type' => ['required', Rule::enum(ReporterType::class)],
            // One tap is enough. Typing a paragraph on a phone is exactly where
            // a 55-year-old teacher abandons the process (spec section 7A).
            'problem' => ['nullable', Rule::in(['not_turning_on', 'damaged', 'missing'])],
            'description' => ['required_without:problem', 'nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('school.photos.max_kilobytes')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('reporter_type') === ReporterType::Student->value
                && ! Setting::bool('student_reporting_enabled', true)) {
                $validator->errors()->add('reporter_type', __('reports.students_disabled'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'reporter_name' => __('reports.fields.reporter_name'),
            'reporter_type' => __('reports.fields.reporter_type'),
            'description' => __('reports.fields.description'),
            'photo' => __('reports.fields.photo'),
        ];
    }

    /** The words the director will read, built from the tap-chip and the free text. */
    public function describedProblem(): string
    {
        $parts = [];

        if ($this->filled('problem')) {
            $parts[] = __('reports.problem.'.$this->string('problem'));
        }

        if ($this->filled('description')) {
            $parts[] = trim($this->string('description'));
        }

        return implode("\n", $parts);
    }
}
