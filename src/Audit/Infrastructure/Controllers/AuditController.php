<?php

namespace Src\Audit\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['ADMIN', 'RESPONSABLE'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $logs = DB::table('audit_logs')
            ->where('clinic_id', $user->clinic_id)
            ->orderBy('occurred_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }
}
