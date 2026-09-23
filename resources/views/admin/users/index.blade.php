<x-layouts.admin :title="__('users.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <ul class="mb-6 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
        @foreach ($users as $user)
            <li class="flex min-h-tap flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2.5 sm:px-4">
                <span class="min-w-0 flex-1 font-semibold">{{ $user->name }}</span>
                <span class="text-sm break-all text-grey-700">{{ $user->email }}</span>
            </li>
        @endforeach
    </ul>

    <p class="max-w-prose text-grey-700">{{ __('users.help') }}</p>

    <pre class="mt-3 overflow-x-auto rounded border border-grey-200 bg-white p-3 font-mono text-sm">{{ __('users.create_command') }}
{{ __('users.password_command') }}</pre>
</x-layouts.admin>
