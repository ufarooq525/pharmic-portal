<x-layouts.app title="Master data · Medical reps" description="Medical reps, their territories and the customers linked to them.">
    <x-masters-tabs current="reps" />

    <section class="card" aria-labelledby="reps-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="reps-h" class="card-title">Medical reps</h2>
            @if ($canEdit)<a href="#rep-form" data-drawer="#rep-form" data-drawer-title="Add a medical rep" class="btn btn-primary btn-sm">+ Add rep</a>@endif
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                <tr>
                    <th scope="col">Medical rep</th><th scope="col">Territory</th><th scope="col">Phone</th><th scope="col" class="r">Base salary</th>
                    <th scope="col" class="r">Customers linked</th><th scope="col">Status</th>@if ($canEdit)<th scope="col"><span class="sr-only">Edit</span></th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($reps as $rep)
                    <tr>
                        <td class="font-bold">{{ $rep->name }}</td>
                        <td>{{ $rep->territory->name }}</td>
                        <td class="num">{{ $rep->phone ?: '–' }}</td>
                        <td class="r">{{ fmt_num($rep->base_salary) }}</td>
                        <td class="r">{{ fmt_num($linked[$rep->id] ?? 0) }}</td>
                        <td><x-chip :tone="$rep->is_active ? 'ok' : 'mute'">{{ $rep->is_active ? 'Active' : 'Inactive' }}</x-chip></td>
                        @if ($canEdit)
                            <td class="text-right"><a href="{{ route('reps.index', ['edit' => $rep->id]) }}#rep-form" data-drawer="#rep-form" class="btn btn-sm">Edit</a></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="m-0 text-[12px] text-muted">A customer can be shared between two reps with a 50/50 split, for example between Mardan 1 and Mardan 2. Link customers to reps on the Customers page.</p>
    </section>

    @if ($canEdit)
        @php($rep = $reps->firstWhere('id', $editing))
        <section id="rep-form" class="card scroll-mt-4" aria-labelledby="repf-h" data-drawer-source>
            <h2 id="repf-h" class="card-title">{{ $rep ? 'Edit '.$rep->name : 'Add a medical rep' }}</h2>
            <form method="post" action="{{ $rep ? route('reps.update', $rep) : route('reps.store') }}" class="grid gap-4 sm:grid-cols-[repeat(auto-fit,minmax(170px,1fr))]">
                @csrf
                @if ($rep) @method('put') @endif
                <div class="flex flex-col gap-1.5">
                    <label for="rep-name" class="label">Name</label>
                    <input id="rep-name" name="name" required maxlength="100" value="{{ old('name', $rep?->name) }}" class="field">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="rep-ter" class="label">Territory</label>
                    <input id="rep-ter" name="territory" required maxlength="80" list="territories" value="{{ old('territory', $rep?->territory->name) }}" class="field">
                    <datalist id="territories">
                        @foreach ($territories as $t)<option value="{{ $t->name }}">@endforeach
                    </datalist>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="rep-phone" class="label">Phone (WhatsApp)</label>
                    <input id="rep-phone" name="phone" maxlength="30" inputmode="tel" value="{{ old('phone', $rep?->phone) }}" class="field">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="rep-sal" class="label">Base salary, Rs</label>
                    <input id="rep-sal" name="base_salary" type="number" min="0" step="1" value="{{ old('base_salary', $rep?->base_salary) }}" class="field num">
                </div>
                @if ($rep)
                    <label class="flex items-center gap-2 self-end pb-3 font-semibold">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rep->is_active)) class="size-4"> Active
                    </label>
                @endif
                <div class="flex flex-wrap gap-2 sm:col-span-full">
                    <button class="btn btn-primary">{{ $rep ? 'Save' : 'Add rep' }}</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
