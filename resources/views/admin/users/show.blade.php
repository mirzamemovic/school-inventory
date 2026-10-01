<x-layouts.admin :title="$user->name" :back="route('admin.users.index')" :back-label="__('users.title')">
    <x-flash />

    <div class="max-w-md space-y-6">
        @if ($link)
            {{-- Shown this once. Only a hash of it is kept, so it can never be
                 shown again: a lost link is replaced, not recovered. --}}
            <section class="rounded border border-grey-200 bg-white p-4 sm:p-6" aria-labelledby="link-heading">
                <h2 id="link-heading" class="text-lg font-bold">{{ __('users.link.heading') }}</h2>
                <p class="mt-1 font-semibold text-grey-900">{{ __('users.link.works_until', ['time' => $link['expires_at']]) }}</p>
                <p class="mt-1 text-grey-700">{{ __('users.link.how', ['name' => $user->name]) }}</p>

                <div class="mx-auto mt-4 w-56" role="img" aria-label="{{ __('users.link.heading') }}">
                    {!! $qrSvg !!}
                </div>

                <p class="mt-4 text-sm text-grey-600">{{ __('users.link.scan') }}</p>
                <input type="text" readonly value="{{ $link['url'] }}" data-select-on-focus aria-label="{{ __('users.link.copy') }}"
                       class="mt-1 block min-h-tap w-full rounded border border-grey-300 bg-grey-50 px-3 font-mono text-xs text-grey-800">
                <p class="mt-3 text-sm font-semibold text-grey-800">{{ __('users.link.shown_once') }}</p>
            </section>
        @elseif ($user->isWaitingForPassword() && ! $user->isDeactivated())
            <section class="rounded border border-status-attention-soft bg-status-attention-soft px-4 py-3">
                <p class="font-semibold text-status-attention">{{ __('users.waiting', ['name' => $user->name]) }}</p>
                @can('resetPassword', $user)
                    <p class="mt-1 text-grey-800">{{ __('users.waiting_help') }}</p>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.working') }}">{{ __('users.new_link') }}</button>
                    </form>
                @endcan
            </section>
        @endif

        @php
            $facts = array_filter([
                __('users.labels.status') => null,
                __('users.labels.email') => $user->email,
                __('users.labels.added') => $user->created_at?->format('j M Y, H:i') ?? __('common.not_set'),
                __('users.labels.password_chosen') => $user->password_set_at?->format('j M Y, H:i') ?? __('users.not_yet'),
                __('users.labels.last_signed_in') => $user->last_login_at
                    ? $user->last_login_at->format('j M Y, H:i').' ('.$user->last_login_at->diffForHumans().')'
                    : __('users.never'),
                __('users.labels.deactivated') => $user->deactivated_at?->format('j M Y, H:i') ?? false,
            ], fn ($value) => $value !== false);
        @endphp
        <dl class="divide-y divide-grey-200 rounded border border-grey-200 bg-white text-sm">
            @foreach ($facts as $label => $value)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 px-3 py-2.5 sm:px-4">
                    <dt class="w-36 shrink-0 text-grey-600">{{ $label }}</dt>
                    <dd class="min-w-0 break-all text-grey-900 tabular-nums">
                        @if ($value === null)
                            <x-status-badge :status="$user->status()" />
                        @else
                            {{ $value }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            @can('update', $user)
                <a href="{{ route('admin.users.edit', $user) }}" class="link-action">{{ __('users.edit') }}</a>
            @endcan
            @if ($user->is($me))
                <a href="{{ route('admin.account.password') }}" class="link-action">{{ __('users.change_my_password') }}</a>
            @endif
        </div>

        @if (! $user->isWaitingForPassword())
            @can('resetPassword', $user)
                <section class="border-t border-grey-200 pt-5">
                    <p class="mb-3 max-w-prose text-grey-700">{{ __('users.reset_help') }}</p>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}"
                          data-confirm="{{ __('users.reset_confirm', ['name' => $user->name]) }}">
                        @csrf
                        <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.working') }}">{{ __('users.reset') }}</button>
                    </form>
                </section>
            @endcan
        @endif

        @if ($user->isDeactivated())
            <section class="border-t border-grey-200 pt-5">
                @can('reactivate', $user)
                    <p class="mb-3 max-w-prose text-grey-700">{{ __('users.reactivate_help') }}</p>
                    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                        @csrf
                        <button type="submit" class="btn-secondary" data-busy-text="{{ __('common.working') }}">{{ __('users.reactivate') }}</button>
                    </form>
                @else
                    <p class="max-w-prose text-sm text-grey-700">{{ __('users.full', ['max' => \App\Models\User::MAX_ACTIVE]) }}</p>
                @endcan
            </section>
        @else
            @can('deactivate', $user)
                {{-- Destructive, so last, apart, and never navy. --}}
                <section class="border-t border-grey-200 pt-5">
                    <p class="mb-3 max-w-prose text-grey-700">{{ __('users.deactivate_help') }}</p>
                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}"
                          data-confirm="{{ __('users.deactivate_confirm', ['name' => $user->name]) }}">
                        @csrf
                        <button type="submit" class="btn-danger w-full sm:w-auto" data-busy-text="{{ __('common.working') }}">{{ __('users.deactivate') }}</button>
                    </form>
                </section>
            @endcan
        @endif
    </div>
</x-layouts.admin>
