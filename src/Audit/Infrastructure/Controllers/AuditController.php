<?php

namespace Src\Audit\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('view-audit');
        $user = $request->user();

        $logs = DB::table('audit_logs')
            ->where('clinic_id', $user->clinic_id)
            ->orderBy('occurred_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }
}
