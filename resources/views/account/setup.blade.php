<x-layouts.public :title="__('users.setup.title')">
    <h1 class="text-2xl leading-tight font-bold tracking-tight">{{ __('users.setup.title') }}</h1>
    <p class="mt-2 text-lg break-words text-grey-700">{{ __('users.setup.for', ['name' => $user->name, 'email' => $user->email]) }}</p>

    <x-flash />

    {{-- Opening this page changed nothing; only saving uses the link up. --}}
    <form method="POST" action="{{ $action }}" class="mt-6">
        @csrf

        <input type="hidden" name="email" value="{{ $user->email }}" autocomplete="username">

        <x-field :label="__('users.password.new')" name="password"
                 :help="__('auth.password_hint', ['length' => \App\Support\AdminPassword::MIN_LENGTH])" required>
            <x-input name="password" type="password" autocomplete="new-password" autofocus required
                     minlength="{{ \App\Support\AdminPassword::MIN_LENGTH }}" />
        </x-field>

        <x-field :label="__('users.password.new_again')" name="password_confirmation" required>
            <x-input name="password_confirmation" type="password" autocomplete="new-password" required />
        </x-field>

        <button type="submit" data-busy-text="{{ __('common.saving') }}"
                class="flex min-h-14 w-full items-center justify-center rounded bg-navy px-4 text-lg font-semibold text-white hover:bg-navy-deep">
            {{ __('users.setup.button') }}
        </button>
    </form>
</x-layouts.public>
