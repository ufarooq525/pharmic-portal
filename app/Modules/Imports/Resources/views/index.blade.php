@php
    $statuses = ['confirmed' => ['Confirmed', 'ok'], 'needs_review' => ['Needs review', 'warn'], 'blocked' => ['Blocked', 'bad']];
    $layouts = ['matrix' => 'SoftTouch product grid', 'lineitem' => 'Line items with bonus', 'monthly' => 'Monthly columns'];
    $canUpload = auth()->user()->hasRole('accounts', 'data_entry');
    $q = ['period' => $period->format('Y-m')];
    // Default to the first distributor whose file is not confirmed yet.
    $current = $distributors->firstWhere('id', $selected)
        ?? $distributors->first(fn ($d) => ($imports->get($d->id)?->status) !== 'confirmed')
        ?? $distributors->first();
    $currentImport = $current ? $imports->get($current->id) : null;
@endphp
<x-layouts.app title="Imports" description="Upload and review distributor customer-wise files for {{ $period->format('F Y') }}.">
    <x-page-header title="Imports" sub="Upload each distributor’s customer-wise PDF. The portal recognises its layout, checks the totals and holds it for review before stock or reports change." />

    <div class="flex flex-wrap items-start gap-5">
        <nav aria-label="Distributors" class="flex w-full max-w-[320px] flex-[1_1_250px] flex-col gap-1 rounded-[20px] bg-surface p-2.5 shadow-[var(--sky-shadow)]">
            @foreach ($distributors as $d)
                @php($import = $imports->get($d->id))
                @php([$label, $tone] = $import ? $statuses[$import->status] : ['Not received', 'mute'])
                <a href="{{ route('imports.index', $q + ['distributor' => $d->id]) }}" @if ($current?->id === $d->id) aria-current="page" @endif
                   class="flex min-h-14 items-center justify-between gap-2.5 rounded-[14px] border-2 px-3 py-2.5 text-ink no-underline {{ $current?->id === $d->id ? 'border-accent bg-soft' : 'border-transparent hover:bg-sunk' }}">
                    <span class="flex min-w-0 flex-col"><b class="font-bold">{{ $d->name }}</b><span class="text-[12px] text-muted">{{ $d->city }}</span></span>
                    <x-chip :tone="$tone">{{ $label }}</x-chip>
                </a>
            @endforeach
        </nav>

        <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
            @if ($current && $currentImport)
                <section class="card" aria-labelledby="cur-h">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <h2 id="cur-h" class="m-0 text-[22px] font-extrabold tracking-[-0.015em]">{{ $current->name }} · {{ $period->format('F Y') }}</h2>
                            <span class="text-muted">{{ $currentImport->original_name }} · {{ $layouts[$currentImport->layout] ?? $currentImport->layout }} · {{ fmt_num($currentImport->parsed_units) }} units · {{ count($currentImport->customers) }} customers</span>
                        </div>
                        <x-chip :tone="$statuses[$currentImport->status][1]" class="text-[13px]">{{ $statuses[$currentImport->status][0] }}</x-chip>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('imports.show', $currentImport) }}" class="btn btn-primary">{{ $currentImport->status === 'confirmed' ? 'Open the customer-wise data' : 'Review and confirm' }}</a>
                        <a href="{{ route('imports.file', $currentImport) }}" class="btn">Open the PDF</a>
                    </div>
                    @if ($currentImport->status !== 'confirmed' && $canUpload)
                        <p class="m-0 text-[13px] text-muted">Wrong file? Upload another below; it replaces this one.</p>
                    @endif
                </section>
            @endif

            @if ($current && $canUpload && ($currentImport?->status !== 'confirmed'))
                <form method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="distributor_id" value="{{ $current->id }}">
                    <label for="file" class="flex min-h-[260px] cursor-pointer flex-col items-center justify-center gap-2.5 rounded-[20px] border-2 border-dashed border-line2 bg-surface p-6 text-center">
                        <span class="flex size-16 items-center justify-center rounded-[20px] bg-soft text-accent-text">
                            <svg aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                        </span>
                        <span class="text-[20px] font-extrabold">Upload {{ $current->name }}’s {{ $period->format('F') }} PDF</span>
                        <span class="text-muted">Expected layout: {{ $layouts[$current->layout] ?? 'not known yet, the portal will detect it' }}</span>
                        <input id="file" name="file" type="file" accept="application/pdf,.pdf" required
                               class="mt-1 max-w-full text-[13px] file:mr-3 file:rounded-[10px] file:border-0 file:bg-seg file:px-3 file:py-2 file:font-bold file:text-ink">
                    </label>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label for="month" class="label">Month the file covers</label>
                            <input id="month" name="period" type="month" required value="{{ old('period', $period->format('Y-m')) }}" class="field w-auto">
                        </div>
                        <button class="btn btn-primary">Read the file</button>
                        <span class="text-[13px] text-muted">Up to 10 MB. Nothing changes stock or reports until it is confirmed.</span>
                    </div>
                </form>
            @elseif (! $canUpload && ! $currentImport)
                <x-empty>No file from {{ $current?->name }} for {{ $period->format('F Y') }} yet.</x-empty>
            @endif
        </div>
    </div>
</x-layouts.app>
