<x-layouts.app title="Master data · Name matching" description="How each distributor spells and codes each product in its PDF.">
    <x-masters-tabs current="aliases" />

    <section class="card" aria-labelledby="al-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="al-h" class="card-title">How each distributor names the products</h2>
            <form method="get" class="flex items-end gap-2">
                <label for="al-d" class="sr-only">Distributor</label>
                <select id="al-d" name="distributor" class="field w-auto" data-autosubmit>
                    <option value="">All distributors</option>
                    @foreach ($distributors as $d)
                        <option value="{{ $d->id }}" @selected($distributorId === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn">Show</button></noscript>
            </form>
        </div>
        @if ($aliases->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Distributor</th><th scope="col">Item code</th><th scope="col">Name in the file</th><th scope="col">Product</th>@if ($canEdit)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($aliases as $a)
                        <tr>
                            <td class="whitespace-nowrap">{{ $a->distributor->name }}</td>
                            <td class="num text-muted">{{ $a->code_in_file ?? '–' }}</td>
                            <td class="font-mono text-[13px]">{{ $a->name_in_file }}</td>
                            <td class="font-semibold">{{ $a->product->name }}</td>
                            @if ($canEdit)
                                <td class="text-right">
                                    <form method="post" action="{{ route('distributors.unlink', [$a->distributor_id, $a]) }}" data-ajax data-confirm="Unlink “{{ $a->name_in_file }}”? The next file that uses it will ask you to link it again.">
                                        @csrf
                                        @method('delete')
                                        <button class="btn btn-sm">Unlink</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $aliases->links() }}
        @else
            <x-empty>No product names linked yet.</x-empty>
        @endif
        <p class="m-0 text-[12px] text-muted">Each spelling is linked once on the review screen and recognised in every later file. Where a file prints the distributor’s own item code, the code is kept too, so a re-spelled name is still recognised.</p>
    </section>
</x-layouts.app>
