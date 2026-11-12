@php
    $confirmed = \App\Modules\Imports\Enums\ImportStatus::Confirmed;
    $layouts = \App\Modules\Imports\Enums\ReportLayout::options();
    $canUpload = auth()->user()->hasRole('accounts', 'data_entry');
    $q = ['period' => $period->format('Y-m')];
    // Default to the first distributor whose file is not confirmed yet.
    $current = $distributors->firstWhere('id', $selected)
        ?? $distributors->first(fn ($d) => ($imports->get($d->id)?->status) !== $confirmed)
        ?? $distributors->first();
    $currentImport = $current ? $imports->get($current->id) : null;
@endphp
<x-layouts.app title="Imports" description="Upload and review distributor customer-wise files for {{ $period->format('F Y') }}.">
    <x-page-header title="Imports" sub="Upload each distributor’s customer-wise PDF. The portal recognises its layout, checks the totals and holds it for review before stock or reports change." />

    <div class="flex flex-wrap items-start gap-5">
        <nav aria-label="Distributors" class="flex w-full max-w-[320px] flex-[1_1_250px] flex-col gap-1 rounded-[20px] bg-surface p-2.5 shadow-[var(--sky-shadow)]">
            @foreach ($distributors as $d)
                @php($import = $imports->get($d->id))
                @php([$label, $tone] = $import ? [$import->status->label(), $import->status->tone()] : ['Not received', 'mute'])
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
                            <span class="text-muted">{{ $currentImport->original_name }} · {{ $currentImport->layout?->label() }} · {{ fmt_num($currentImport->parsed_units) }} units · {{ count($currentImport->customers) }} customers</span>
                        </div>
                        <x-chip :tone="$currentImport->status->tone()" class="text-[13px]">{{ $currentImport->status->label() }}</x-chip>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('imports.show', $currentImport) }}" class="btn btn-primary">{{ $currentImport->status === $confirmed ? 'Open the customer-wise data' : 'Review and confirm' }}</a>
                        <a href="{{ route('imports.file', $currentImport) }}" class="btn">Open the PDF</a>
                        @if ($currentImport->status === $confirmed && auth()->user()->hasRole())
                            <form method="post" action="{{ route('imports.reverse', $currentImport) }}" data-confirm="Revert {{ possessive($current->name) }} {{ $period->format('F') }} import and remove the file? Its sales come out, the units go back into stock and any invoice for the month is cancelled with a credit note.">
                                @csrf
                                <input type="hidden" name="remove_file" value="1">
                                <button class="btn border-transparent bg-bad-soft text-bad" data-busy-label="Reverting…">Revert and remove</button>
                            </form>
                        @elseif ($currentImport->status !== $confirmed && $canUpload)
                            <form method="post" action="{{ route('imports.discard', $currentImport) }}" data-confirm="Discard this file? Nothing has been posted from it.">
                                @csrf
                                @method('delete')
                                <button class="btn" data-busy-label="Discarding…">Discard</button>
                            </form>
                        @endif
                    </div>
                    @php($failed = collect($currentImport->checks)->reject(fn ($c) => $c['ok']))
                    @if ($failed->isNotEmpty() && $currentImport->status !== $confirmed)
                        <div class="flex items-start gap-2.5 rounded-[14px] bg-bad-soft px-3.5 py-3 font-semibold text-bad" role="alert">
                            <span aria-hidden="true" class="font-extrabold">✕</span>
                            <span>Wrong file? {{ $failed->pluck('message')->join(' ') }} Upload the right one below; it replaces this file.</span>
                        </div>
                    @elseif ($currentImport->status !== $confirmed && $canUpload)
                        <p class="m-0 text-[13px] text-muted">Wrong file? Upload another below; it replaces this one.</p>
                    @endif
                </section>
            @endif

            @if ($current && $canUpload && ($currentImport?->status !== $confirmed))
                <form id="upload" method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-upload>
                    @csrf
                    <input type="hidden" name="distributor_id" value="{{ $current->id }}">
                    <label for="file" data-dropzone class="relative flex min-h-[260px] cursor-pointer flex-col items-center justify-center gap-2.5 rounded-[20px] border-2 border-dashed border-line2 bg-surface p-6 text-center transition-colors data-[over]:border-accent data-[over]:bg-soft">
                        <span class="flex size-16 items-center justify-center rounded-[20px] bg-soft text-accent-text">
                            <svg aria-hidden="true" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                        </span>
                        <span class="text-[20px] font-extrabold">Upload {{ possessive($current->name) }} {{ $period->format('F') }} PDF</span>
                        <span class="text-muted">Drop the file here or click to choose · Expected layout: {{ $current->layout?->label() ?? 'not known yet, the portal will detect it' }}</span>
                        <span data-file-name class="hidden rounded-full bg-seg px-3 py-1 text-[13px] font-bold"></span>
                        <input id="file" name="file" type="file" accept="application/pdf,.pdf" required class="absolute size-px opacity-0">
                    </label>

                    {{-- Shown by app.js while the file uploads and is read, like the approved prototype. --}}
                    <section data-upload-progress class="card hidden" aria-live="polite">
                        <h2 class="m-0 text-[20px] font-extrabold">Reading <span data-progress-file></span></h2>
                        <div class="flex flex-col gap-1.5">
                            <div class="flex justify-between text-[13px] font-bold"><span data-progress-label>Uploading</span><span data-progress-pct class="num">0%</span></div>
                            <span class="bar-track"><span data-progress-bar class="bar-fill block w-0 transition-[width]"></span></span>
                        </div>
                        <ol data-progress-steps class="m-0 flex list-none flex-col gap-3 p-0">
                            @foreach (['Recognising the layout', 'Reading the header', 'Reading customers and lines', 'Checking against the grand total'] as $i => $label)
                                <li class="flex items-start gap-3 opacity-55" data-step>
                                    <span data-step-dot class="flex size-7 flex-none items-center justify-center rounded-full bg-seg text-[13px] font-extrabold text-nav">{{ $i + 1 }}</span>
                                    <span class="flex flex-col"><b class="font-bold">{{ $label }}</b><span data-step-result class="font-mono text-[12.5px] text-muted"></span></span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                    <div data-upload-errors class="hidden rounded-[14px] bg-bad-soft px-3.5 py-3 font-semibold text-bad" role="alert"></div>

                    <div class="flex flex-wrap items-end gap-3" data-upload-controls>
                        <div class="flex flex-col gap-1.5">
                            <label for="month" class="label">Month the file covers</label>
                            <input id="month" name="period" type="month" required value="{{ old('period', $period->format('Y-m')) }}" class="field w-auto">
                        </div>
                        <button class="btn btn-primary" data-busy-label="Reading…">Read the file</button>
                        <span class="text-[13px] text-muted">Up to 10 MB. Nothing changes stock or reports until it is confirmed.</span>
                    </div>
                </form>
            @elseif (! $canUpload && ! $currentImport)
                <x-empty>No file from {{ $current?->name }} for {{ $period->format('F Y') }} yet.</x-empty>
            @endif
        </div>
    </div>
</x-layouts.app>
