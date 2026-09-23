<x-layouts.admin :title="__('settings.title')">
    <x-flash />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-md">
        @csrf
        @method('PUT')

        <x-field :label="__('settings.labels.school_name')" name="school_name" :help="__('settings.help.school_name')" required>
            <x-input name="school_name" :value="old('school_name', $schoolName)" required />
        </x-field>

        <div class="mb-4">
            <label class="flex min-h-tap items-center gap-2 text-sm font-medium text-grey-700">
                <input type="checkbox" name="student_reporting_enabled" value="1" class="size-5 rounded border-grey-300"
                       @checked(old('student_reporting_enabled', $studentReporting))>
                {{ __('settings.labels.student_reporting_enabled') }}
            </label>
            <p class="mt-1 text-xs text-grey-500">{{ __('settings.help.student_reporting_enabled') }}</p>
        </div>

        <x-field :label="__('settings.labels.academic_year')" name="academic_year" :help="__('settings.help.academic_year')">
            <x-input name="academic_year" :value="old('academic_year', $academicYear)" placeholder="2026/2027" />
        </x-field>

        <x-field :label="__('settings.labels.notification_email')" name="notification_email" :help="__('settings.help.notification_email')">
            <x-input name="notification_email" type="email" :value="old('notification_email', $notificationEmail)" />
        </x-field>

        <button type="submit" class="inline-flex min-h-tap items-center rounded bg-navy px-5 font-semibold text-white hover:bg-navy-deep">
            {{ __('common.save_changes') }}
        </button>
    </form>
</x-layouts.admin>
