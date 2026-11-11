<?php

namespace App\Modules\Access\Http\Controllers\Web;

use App\Modules\Access\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController
{
    public function __invoke(Request $request): View
    {
        $action = $request->string('action')->toString();

        return view('access::activity', [
            'logs' => ActivityLog::with('user:id,name,role')
                ->when($action !== '', fn ($q) => $q->where('action', $action))
                ->latest('id')->paginate(50)->withQueryString(),
            'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action'),
            'action' => $action,
        ]);
    }
}
