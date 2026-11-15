{{--
    The permission checkboxes, grouped by area.
    mode "role":   ticks are the role's permissions.
    mode "person": the role's permissions show ticked and locked ("From role"); the rest are extras.
    resources/js/app.js keeps it live: changing the role re-ticks, and an action ticks its "view".
--}}
@php
    use App\Modules\Access\Enums\Permission;

    $isOwnerRole ??= false;
    $ticked = array_values((array) $ticked);
@endphp
<fieldset class="m-0 flex flex-col gap-3 border-0 p-0" data-permissions data-mode="{{ $mode }}">
    <legend class="label mb-1">{{ $mode === 'role' ? 'What this role can do' : 'What they can do' }}</legend>
    <p class="m-0 text-[12.5px] text-muted" data-owner-note @unless ($isOwnerRole) hidden @endunless>Owners can do everything, so there is nothing to tick.</p>
    @if ($mode === 'person')
        <p class="m-0 text-[12.5px] text-muted" data-extras-note @if ($isOwnerRole) hidden @endif>Ticks from the role are locked. Tick anything else to give this person an extra permission.</p>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        <label for="perm-q-{{ $mode }}" class="sr-only">Find a permission</label>
        <input id="perm-q-{{ $mode }}" type="search" class="field min-w-48 flex-1" placeholder="Find a permission, e.g. invoice" autocomplete="off" data-perm-search>
        <button type="button" class="link-btn" data-perm-toggle-all>Expand all</button>
    </div>
    <p class="m-0 text-[13px] text-muted" data-perm-empty hidden></p>

    <div class="perm-groups">
        @foreach (Permission::grouped() as $group => $permissions)
            @php $bodyId = 'perm-'.$mode.'-'.$loop->index; @endphp
            <div class="perm-group" data-perm-group data-open="0">
                <div class="flex items-center justify-between gap-2">
                    <button type="button" class="perm-group-toggle" aria-expanded="false" aria-controls="{{ $bodyId }}" data-perm-group-toggle>
                        <svg class="perm-chevron" viewBox="0 0 20 20" width="14" height="14" aria-hidden="true"><path d="M7 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="perm-group-name">{{ $group }}</span>
                        <span class="perm-count" data-perm-count></span>
                    </button>
                    @if ($mode === 'role' && count($permissions) > 1)
                        <button type="button" class="link-btn" data-select-group aria-label="Tick all in {{ $group }}">All</button>
                    @endif
                </div>
                <div id="{{ $bodyId }}" class="flex flex-col gap-1" data-perm-group-body hidden>
                @foreach ($permissions as $p)
                    @php
                        $inherited = $mode === 'person' && ($isOwnerRole || in_array($p->value, $fromRole, true));
                        $isTicked = in_array($p->value, $ticked, true);
                        $locked = ! $canGive($p->value);
                    @endphp
                    <label class="perm">
                        <input type="checkbox" name="permissions[]" value="{{ $p->value }}" class="size-4"
                               data-perm="{{ $p->value }}" @if ($p->requires()) data-requires="{{ $p->requires()->value }}" @endif
                               data-extra="{{ $isTicked ? '1' : '0' }}" @if ($locked) data-locked @endif
                               @checked($inherited || $isTicked) @disabled($inherited || $locked)>
                        <span class="min-w-0">
                            <span class="perm-name">{{ $p->label() }}</span>
                            <span class="perm-tag" data-from-role @unless ($inherited) hidden @endunless>From role</span>
                            @if ($locked)<span class="perm-tag perm-tag-mute">You can’t give this</span>@endif
                            @if ($p->hint())<span class="perm-hint">{{ $p->hint() }}</span>@endif
                        </span>
                    </label>
                    {{-- An extra this manager cannot give is kept as it is (disabled boxes are not sent). --}}
                    @if ($locked && $isTicked && ! $inherited)<input type="hidden" name="permissions[]" value="{{ $p->value }}">@endif
                @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <p class="m-0 text-[12.5px] font-semibold text-muted" data-perm-summary aria-live="polite"></p>
</fieldset>
