{{-- Search box above the People or Roles table (?q=, same tab). --}}
<form method="get" action="{{ route('users.index') }}" class="mt-4 flex flex-wrap items-end gap-2" role="search">
    @if ($tabValue)<input type="hidden" name="tab" value="{{ $tabValue }}">@endif
    <div class="flex min-w-56 flex-1 flex-col gap-1.5">
        <label for="list-q" class="label">Search</label>
        <input id="list-q" name="q" type="search" value="{{ $search }}" placeholder="{{ $placeholder }}" class="field" maxlength="100">
    </div>
    <button class="btn">Search</button>
    @if ($search !== '')
        <a href="{{ route('users.index', array_filter(['tab' => $tabValue])) }}" class="btn">Clear</a>
    @endif
</form>
