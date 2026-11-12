<?php

namespace App\Support\Actions;

use App\Modules\Access\Models\ActivityLog;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * One piece of business logic with a single public handle() method. Controllers, console
 * commands and other actions call it the same way: SomeAction::run(...) or via injection.
 */
abstract class Action
{
    public static function run(mixed ...$arguments): mixed
    {
        return app(static::class)->handle(...$arguments);
    }

    /** All-or-nothing: either every write in the closure is saved, or none is. */
    protected function transaction(Closure $work): mixed
    {
        return DB::transaction($work);
    }

    /** Writes a line to the append-only activity log. */
    protected function log(string $action, string $detail): void
    {
        ActivityLog::record($action, $detail);
    }
}
