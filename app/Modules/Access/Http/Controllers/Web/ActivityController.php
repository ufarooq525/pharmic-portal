<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Repositories\ActivityLogRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController
{
    public function __invoke(Request $request, ActivityLogRepository $activity): View
    {
        $action = $request->string('action')->toString();

        return view('access::activity', [
            'logs' => $activity->latest($action ?: null),
            'actions' => $activity->actions(),
            'action' => $action,
        ]);
    }
}
