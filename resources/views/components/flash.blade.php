@if (session('status'))
    <p role="status" class="mb-5 rounded border border-status-ok-soft bg-status-ok-soft px-4 py-3 font-semibold text-status-ok">
        {{ session('status') }}
    </p>
@endif

@if ($errors->any())
    <div role="alert" class="mb-5 rounded border border-status-broken-soft bg-status-broken-soft px-4 py-3 text-status-broken">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
