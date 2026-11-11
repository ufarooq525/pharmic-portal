@php
    $statuses = [
        'confirmed' => ['Confirmed', 'ok'],
        'needs_review' => ['Needs review', 'warn'],
        'blocked' => ['Blocked', 'bad'],
    ];
    $canUpload = auth()->user()->hasRole('accounts', 'data_entry');
    $layouts = ['matrix' => 'Product grid (SoftTouch)', 'lineitem' => 'Line items with bonus', 'monthly' => 'Monthly columns'];
@endphp
<x-layouts.app title="Imports" description="Upload and review distributor customer-wise files for {{ $period->format('F Y') }}.">
    <x-page-header title="Imports" sub="Upload each distributor’s customer-wise PDF for {{ $period->format('F Y') }}. The portal reads its layout, checks the totals and holds it for review. Nothing changes stock or reports until it is confirmed." />

    <div class="grid gap-5 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        <section class="card flex flex-col gap-4" aria-labelledby="files-h">
            <h2 id="files-h" class="card-title">Files for {{ $period->format('F Y') }}</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Distributor</th><th scope="col">Layout</th><th scope="col" class="r">Units</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody>
                    @foreach ($distributors as $d)
                        @php($import = $imports->get($d->id))
                        <tr>
                            <td><span class="block font-bold">{{ $d->name }}</span><span class="text-[12px] text-muted">{{ $d->city }}</span></td>
                            <td class="text-[13px]">{{ $layouts[$d->layout] ?? 'Not known yet' }}</td>
                            <td class="r">{{ $import ? fmt_num($import->parsed_units) : '–' }}</td>
                            <td>
                                @if ($import)
                                    <x-chip :tone="$statuses[$import->status][1]">{{ $statuses[$import->status][0] }}</x-chip>
                                @else
                                    <x-chip>Not received</x-chip>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($import)
                                    <a href="{{ route('imports.show', $import) }}" class="btn btn-sm">{{ $import->status === 'confirmed' ? 'View' : 'Review' }}</a>
                                @elseif ($canUpload)
                                    <a href="{{ route('imports.index', ['period' => $period->format('Y-m'), 'distributor' => $d->id]) }}#upload" class="btn btn-sm">Upload</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($canUpload)
            <section id="upload" class="card flex flex-col gap-4" aria-labelledby="up-h">
                <h2 id="up-h" class="card-title">Upload a file</h2>
                <form method="post" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    <div class="flex flex-col gap-1.5">
                        <label for="distributor_id" class="label">Distributor</label>
                        <select id="distributor_id" name="distributor_id" required class="field">
                            <option value="">Choose…</option>
                            @foreach ($distributors as $d)
                                <option value="{{ $d->id }}" @selected((int) old('distributor_id', $selected) === $d->id)>{{ $d->name }}, {{ $d->city }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="month" class="label">Month the file covers</label>
                        <input id="month" name="period" type="month" required value="{{ old('period', $period->format('Y-m')) }}" class="field">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="file" class="label">Customer-wise PDF</label>
                        <label for="file" class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-accent/40 bg-accent-soft/40 px-4 py-7 text-center">
                            <svg aria-hidden="true" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-accent"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                            <span class="font-bold">Choose the PDF</span>
                            <span class="text-[12px] text-muted">Up to 10 MB. A new upload replaces an unconfirmed one for the same month.</span>
                        </label>
                        <input id="file" name="file" type="file" accept="application/pdf,.pdf" required class="text-[13px] file:mr-3 file:rounded-lg file:border-0 file:bg-sunk file:px-3 file:py-2 file:font-bold file:text-ink">
                    </div>
                    <button class="btn btn-primary">Read the file</button>
                </form>
            </section>
        @endif
    </div>
</x-layouts.app>
