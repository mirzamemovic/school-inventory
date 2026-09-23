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

        <label class="mb-6 flex min-h-tap items-center gap-2 text-sm text-grey-700">
            <input type="checkbox" name="remember" value="1" class="size-5 rounded border-grey-300">
            {{ __('common.remember_me') }}
        </label>

        <button type="submit" class="flex min-h-tap w-full items-center justify-center rounded bg-navy px-4 text-base font-semibold text-white hover:bg-navy-deep">
            {{ __('common.sign_in') }}
        </button>
    </form>
</x-layouts.public>
