<x-layouts.admin :title="__('users.title')">
    <x-flash />

    <ul class="mb-6 divide-y divide-grey-200 rounded border border-grey-200 bg-white">
        @foreach ($users as $user)
            <li class="flex min-h-tap items-center gap-3 p-3">
                <span class="min-w-0 flex-1 font-medium">{{ $user->name }}</span>
                <span class="text-sm text-grey-600">{{ $user->email }}</span>
            </li>
        @endforeach
    </ul>

    <p class="max-w-prose text-sm text-grey-600">{{ __('users.help') }}</p>

    <pre class="mt-3 overflow-x-auto rounded border border-grey-200 bg-grey-50 p-3 font-mono text-xs">{{ __('users.create_command') }}
{{ __('users.password_command') }}</pre>
</x-layouts.admin>
