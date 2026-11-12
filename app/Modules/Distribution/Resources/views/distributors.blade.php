@php
    use App\Modules\Distribution\Models\Distributor;
    $layouts = \App\Modules\Imports\Enums\ReportLayout::options();
    $bonusSources = \App\Modules\Distribution\Enums\BonusSource::options();
@endphp
<x-layouts.app title="Master data · Distributors" description="Each distributor's file format: report layout, header name, bonus source and product spellings.">
    <x-masters-tabs current="distributors" />

    <div class="flex flex-wrap items-start gap-5">
        <nav aria-label="Distributors" class="flex w-full max-w-[320px] flex-[1_1_250px] flex-col gap-1 rounded-[20px] bg-surface p-2.5 shadow-[var(--sky-shadow)]">
            @foreach ($distributors as $d)
                <a href="{{ route('distributors.index', ['distributor' => $d->id]) }}" @if ($current?->id === $d->id) aria-current="page" @endif
                   class="flex min-h-14 items-center justify-between gap-2.5 rounded-[14px] border-2 px-3 py-2.5 text-ink no-underline {{ $current?->id === $d->id ? 'border-accent bg-soft' : 'border-transparent hover:bg-sunk' }}">
                    <span class="flex min-w-0 flex-col"><b class="font-bold">{{ $d->name }}</b><span class="text-[12px] text-muted">{{ $d->city }} · {{ $d->aliases_count }} product {{ str('link')->plural($d->aliases_count) }}</span></span>
                    @if ($d->layout)
                        <x-chip tone="info">{{ $d->layout->shortLabel() }}</x-chip>
                    @else
                        <x-chip tone="mute">No file yet</x-chip>
                    @endif
                </a>
            @endforeach
        </nav>

        @if ($current)
            <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-4">
                <section class="card" aria-labelledby="profile-h">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <h2 id="profile-h" class="m-0 text-[22px] font-extrabold tracking-[-0.015em]">{{ $current->name }}</h2>
                            <span class="text-muted">{{ $current->city }}{{ $current->phone ? ' · '.$current->phone : '' }} · {{ $current->customers_count }} customers · {{ $current->imports_count }} {{ str('file')->plural($current->imports_count) }} imported</span>
                        </div>
                        @if ($lastImport)
                            <a href="{{ route('imports.show', $lastImport->id) }}" class="chip chip-{{ $lastImport->status->tone() }} text-[13px]">
                                Last file: {{ \Carbon\CarbonImmutable::parse($lastImport->period)->format('M Y') }} · {{ $lastImport->status->label() }}
                            </a>
                        @endif
                    </div>

                    @if ($canEdit)
                        <a href="#profile-form" data-drawer="#profile-form" class="btn btn-sm self-start">Edit file profile</a>
                    @endif
                    <dl class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-[14px] bg-sunk px-3.5 py-3">
                            <dt class="label">Report format</dt>
                            <dd class="m-0 mt-1 font-bold">{{ $current->layout?->label() ?? 'Not known yet, saved from the first confirmed file' }}</dd>
                        </div>
                        <div class="rounded-[14px] bg-sunk px-3.5 py-3">
                            <dt class="label">Name at the top of the file</dt>
                            <dd class="m-0 mt-1 font-bold">{{ $current->header_name ?: 'Not saved yet, first word of the name is used' }}</dd>
                        </div>
                        <div class="rounded-[14px] bg-sunk px-3.5 py-3">
                            <dt class="label">Bonus</dt>
                            <dd class="m-0 mt-1 font-bold">{{ $current->bonus_source->label() }}</dd>
                        </div>
                    </dl>
                    @if ($current->format_notes)
                        <p class="m-0 whitespace-pre-line rounded-[14px] bg-info-soft px-3.5 py-3 text-info">{{ $current->format_notes }}</p>
                    @endif
                </section>

                @if ($canEdit)
                    <section id="profile-form" class="card" aria-labelledby="pf-h" data-drawer-source>
                        <h2 id="pf-h" class="card-title">Edit {{ $current->name }}’s file profile</h2>
                        <form method="post" action="{{ route('distributors.update', $current) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            @method('put')
                            <div class="flex flex-col gap-1.5">
                                <label for="d-name" class="label">Name</label>
                                <input id="d-name" name="name" value="{{ old('name', $current->name) }}" required maxlength="120" class="field">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="d-city" class="label">City</label>
                                <input id="d-city" name="city" value="{{ old('city', $current->city) }}" required maxlength="80" class="field">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="d-phone" class="label">Phone (for WhatsApp)</label>
                                <input id="d-phone" name="phone" value="{{ old('phone', $current->phone) }}" maxlength="30" class="field" inputmode="tel">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="d-header" class="label">Name at the top of its PDF</label>
                                <input id="d-header" name="header_name" value="{{ old('header_name', $current->header_name) }}" maxlength="255" class="field" placeholder="e.g. UMAR DRUG AGENCIES">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="d-layout" class="label">Report format</label>
                                <select id="d-layout" name="layout" class="field">
                                    <option value="">Not known yet (save from the first file)</option>
                                    @foreach ($layouts as $key => $label)
                                        <option value="{{ $key }}" @selected(old('layout', $current->layout?->value) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="d-bonus" class="label">Bonus</label>
                                <select id="d-bonus" name="bonus_source" class="field">
                                    @foreach ($bonusSources as $key => $label)
                                        <option value="{{ $key }}" @selected(old('bonus_source', $current->bonus_source->value) === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <label for="d-notes" class="label">Notes about this distributor’s file</label>
                                <textarea id="d-notes" name="format_notes" rows="3" maxlength="2000" class="field">{{ old('format_notes', $current->format_notes) }}</textarea>
                            </div>
                            <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary">Save profile</button><button type="button" class="btn" data-drawer-close>Cancel</button></div>
                        </form>
                    </section>
                @endif

                <section class="card" aria-labelledby="alias-h">
                    <h2 id="alias-h" class="card-title">How {{ str($current->name)->before(' ') }} names the products</h2>
                    @if ($aliases->isNotEmpty())
                        <div class="table-wrap">
                            <table class="data">
                                <thead><tr><th scope="col">Item code</th><th scope="col">Name in the file</th><th scope="col">Product</th>@if ($canEdit)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead>
                                <tbody>
                                @foreach ($aliases as $a)
                                    <tr>
                                        <td class="num text-muted">{{ $a->code_in_file ?? '–' }}</td>
                                        <td class="font-mono text-[13px]">{{ $a->name_in_file }}</td>
                                        <td class="font-semibold">{{ $a->product->name }}</td>
                                        @if ($canEdit)
                                            <td class="text-right">
                                                <form method="post" action="{{ route('distributors.unlink', [$current, $a]) }}" data-ajax data-confirm="Unlink “{{ $a->name_in_file }}”?">
                                                    @csrf
                                                    @method('delete')
                                                    <button class="btn btn-sm" aria-label="Unlink {{ $a->name_in_file }}">Unlink</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <x-empty>No product names linked yet. They are linked on the review screen of the first file.</x-empty>
                    @endif
                    <p class="m-0 text-[13px] text-muted">Names are matched exactly first. Where the file prints the distributor’s own item code, a re-spelled name with a known code is recognised too. Codes belong to this distributor only; the same number means a different product elsewhere.</p>
                </section>
            </div>
        @endif
    </div>
</x-layouts.app>
