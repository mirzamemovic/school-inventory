<x-layouts.admin :title="__('users.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <p class="mb-5 max-w-prose text-grey-700">{{ __('users.help') }}</p>

    <div class="mb-5 flex flex-wrap items-center gap-x-5 gap-y-2">
        @can('create', \App\Models\User::class)
            <a href="{{ route('admin.users.create') }}" class="btn-primary">{{ __('users.add') }}</a>
        @endcan
        <a href="{{ route('admin.account.password') }}" class="link-action">{{ __('users.change_my_password') }}</a>
    </div>

    @cannot('create', \App\Models\User::class)
        <p class="mb-5 max-w-prose text-sm text-grey-700">{{ __('users.full', ['max' => \App\Models\User::MAX_ACTIVE]) }}</p>
    @endcannot

    {{-- Name, address, state and when they last signed in. Never anything
         about the password beyond whether one has been chosen. --}}
    <ul class="divide-y divide-grey-200 rounded border border-grey-200 bg-white">
        @foreach ($users as $user)
            <li>
                <a href="{{ route('admin.users.show', $user) }}"
                   class="group flex min-h-tap flex-wrap items-center gap-x-5 gap-y-1 px-3 py-3 hover:bg-grey-50 sm:px-4">
                    <span class="min-w-0 flex-1 basis-56">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold group-hover:underline group-hover:decoration-grey-400 group-hover:underline-offset-2 {{ $user->isDeactivated() ? 'text-grey-600' : 'text-grey-900' }}">{{ $user->name }}</span>
                            @if ($user->is($me))
                                <span class="rounded bg-grey-100 px-1.5 py-0.5 text-xs text-grey-700">{{ __('users.you') }}</span>
                            @endif
                        </span>
                        <span class="block text-sm break-all text-grey-700">{{ $user->email }}</span>
                        <span class="block text-sm text-grey-600 tabular-nums">
                            {{ __('users.added_on', ['date' => $user->created_at?->format('j M Y') ?? __('common.not_set')]) }}
                            ·
                            {{ $user->last_login_at
                                ? __('users.last_signed_in', ['time' => $user->last_login_at->diffForHumans()])
                                : __('users.never_signed_in') }}
                        </span>
                    </span>

                    <x-status-badge :status="$user->status()" />
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.admin>
