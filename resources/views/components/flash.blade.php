@if (session('status'))
    <div role="status" class="rounded-2xl bg-ok-soft px-4 py-3 font-semibold text-ok">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div role="alert" class="rounded-2xl bg-warn-soft px-4 py-3 font-semibold text-warn">{{ session('warning') }}</div>
@endif

@if ($errors->any())
    <div role="alert" class="rounded-2xl bg-bad-soft px-4 py-3 text-bad">
        <p class="font-bold">Please fix the following:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
