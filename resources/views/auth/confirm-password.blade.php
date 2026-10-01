<x-layouts.admin :title="__('users.confirm.title')" :back="route('admin.manage')" :back-label="__('manage.title')">
    <x-flash />

    <p class="mb-5 max-w-prose text-grey-700">
        {{ __('users.confirm.help', ['minutes' => \App\Http\Controllers\Auth\ConfirmPasswordController::MINUTES]) }}
    </p>

    <form method="POST" action="{{ route('admin.password.confirm.store') }}" class="max-w-md">
        @csrf

        {{-- The account is named for password managers; it is the one signed in. --}}
        <input type="hidden" name="email" value="{{ auth()->user()->email }}" autocomplete="username">

        <x-field :label="__('common.password')" name="password" required>
            <x-input name="password" type="password" autocomplete="current-password" autofocus required />
        </x-field>

        <button type="submit" class="btn-primary" data-busy-text="{{ __('common.working') }}">
            {{ __('users.confirm.button') }}
        </button>
    </form>
</x-layouts.admin>
