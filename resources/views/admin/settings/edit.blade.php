<x-layouts.admin :title="__('settings.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-md">
        @csrf
        @method('PUT')

        <x-field :label="__('settings.labels.school_name')" name="school_name" :help="__('settings.help.school_name')" required>
            <x-input name="school_name" :value="old('school_name', $schoolName)" required />
        </x-field>

        <div class="mb-5">
            <label class="flex min-h-tap cursor-pointer items-center gap-3 text-base font-semibold text-grey-800">
                <input type="checkbox" name="student_reporting_enabled" value="1" class="size-5 rounded border-grey-300"
                       aria-describedby="student-reporting-help"
                       @checked(old('student_reporting_enabled', $studentReporting))>
                {{ __('settings.labels.student_reporting_enabled') }}
            </label>
            <p id="student-reporting-help" class="text-sm text-grey-600">{{ __('settings.help.student_reporting_enabled') }}</p>
        </div>

        <x-field :label="__('settings.labels.academic_year')" name="academic_year" :help="__('settings.help.academic_year')">
            <x-input name="academic_year" :value="old('academic_year', $academicYear)" placeholder="2026/2027" class="max-w-40" />
        </x-field>

        <x-field :label="__('settings.labels.notification_email')" name="notification_email" :help="__('settings.help.notification_email')">
            <x-input name="notification_email" type="email" :value="old('notification_email', $notificationEmail)" />
        </x-field>

        <button type="submit" class="btn-primary mt-2" data-busy-text="{{ __('common.saving') }}">
            {{ __('common.save_changes') }}
        </button>
    </form>
</x-layouts.admin>
