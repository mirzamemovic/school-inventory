<x-layouts.admin :title="__('users.password.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <p class="mb-5 max-w-prose text-grey-700">{{ __('users.password.help') }}</p>

    <form method="POST" action="{{ route('admin.account.password.update') }}" class="max-w-md">
        @csrf
        @method('PUT')

        <input type="hidden" name="email" value="{{ auth()->user()->email }}" autocomplete="username">

        <x-field :label="__('users.password.current')" name="current_password" required>
            <x-input name="current_password" type="password" autocomplete="current-password" autofocus required />
        </x-field>

        <x-field :label="__('users.password.new')" name="password"
                 :help="__('auth.password_hint', ['length' => \App\Support\AdminPassword::MIN_LENGTH])" required>
            <x-input name="password" type="password" autocomplete="new-password" required
                     minlength="{{ \App\Support\AdminPassword::MIN_LENGTH }}" />
        </x-field>

        <x-field :label="__('users.password.new_again')" name="password_confirmation" required>
            <x-input name="password_confirmation" type="password" autocomplete="new-password" required />
        </x-field>

        <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2">
            <button type="submit" class="btn-primary" data-busy-text="{{ __('common.saving') }}">
                {{ __('users.password.title') }}
            </button>
            <a href="{{ route('admin.manage') }}" class="link-action text-grey-700">{{ __('common.cancel') }}</a>
        </div>
    </form>
</x-layouts.admin>
