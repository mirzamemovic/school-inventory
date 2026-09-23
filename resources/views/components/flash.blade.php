@if (session('status'))
    <p role="status" class="mb-4 rounded border border-status-ok-soft bg-status-ok-soft px-3 py-2 text-sm font-medium text-status-ok">
        {{ session('status') }}
    </p>
@endif

@if ($errors->any())
    <div role="alert" class="mb-4 rounded border border-status-broken-soft bg-status-broken-soft px-3 py-2 text-sm text-status-broken">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
