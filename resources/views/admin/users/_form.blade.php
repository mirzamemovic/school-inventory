{{-- Two fields and nothing else: no password (they choose their own with a
     once-only link) and no role (every user is an admin). --}}
<x-field :label="__('users.labels.name')" name="name" required>
    <x-input name="name" :value="old('name', $user?->name)" maxlength="100" autocomplete="off" autofocus required />
</x-field>

<x-field :label="__('users.labels.email')" name="email" :help="__('users.email_help')" required>
    <x-input name="email" type="email" :value="old('email', $user?->email)" maxlength="255" autocomplete="off" required />
</x-field>
