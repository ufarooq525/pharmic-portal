<x-layouts.app title="Activity log" description="Who did what and when in the portal.">
    <x-page-header title="Activity log" sub="Who did what and when. Entries cannot be edited or deleted." />

    <section class="card" aria-labelledby="act-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="act-h" class="card-title">Recent activity</h2>
            <form method="get" class="flex items-end gap-2">
                <label for="act-filter" class="sr-only">Action</label>
                <select id="act-filter" name="action" class="field w-auto" data-autosubmit>
                    <option value="">All actions</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected($a === $action)>{{ $a }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn">Filter</button></noscript>
            </form>
        </div>
        @if ($logs->isNotEmpty())
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Detail</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="num whitespace-nowrap text-muted">{{ $log->created_at->format('d M Y, H:i') }}</td>
                            <td class="whitespace-nowrap">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="font-semibold whitespace-nowrap">{{ $log->action }}</td>
                            <td>{{ $log->detail }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        @else
            <x-empty>Nothing recorded yet. Uploads, confirmations, rate changes, payments and stock receipts appear here.</x-empty>
        @endif
    </section>
</x-layouts.app>
