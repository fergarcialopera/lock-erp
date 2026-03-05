<?php

namespace Src\Dashboard\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Src\Dashboard\Application\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function index(Request $request)
    {
        $data = $this->dashboardService->getDashboardData($request->user()->clinic_id);

        return response()->json($data);
    }
}
