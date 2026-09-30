<x-layouts.public :title="__('common.sign_in')">
    <h1 class="mb-6 text-2xl font-bold tracking-tight">{{ __('common.sign_in') }}</h1>

    <x-flash />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-field :label="__('common.email')" name="email" required>
            <x-input name="email" type="email" :value="old('email')" autocomplete="username" autofocus required />
        </x-field>

        <x-field :label="__('common.password')" name="password" required>
            <x-input name="password" type="password" autocomplete="current-password" required />
        </x-field>

        <label class="mb-6 flex min-h-tap cursor-pointer items-center gap-3 text-base text-grey-800">
            <input type="checkbox" name="remember" value="1" class="size-5 rounded border-grey-300">
            {{ __('common.remember_me', ['days' => intdiv((int) config('auth.guards.web.remember'), 1440)]) }}
        </label>

        <button type="submit" class="btn-primary w-full" data-busy-text="{{ __('common.working') }}">
            {{ __('common.sign_in') }}
        </button>
    </form>
</x-layouts.public>
