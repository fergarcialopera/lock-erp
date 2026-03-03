<?php

namespace Src\Audit\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Audit\Application\Services\AuditService;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService
    ) {
    }

    public function index(Request $request)
    {
        Gate::authorize('view-audit');

        $logs = $this->auditService->list($request->user()->clinic_id);

        return response()->json($logs);
    }
}
