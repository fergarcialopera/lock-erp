<?php

namespace Src\Lockers\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Lockers\Application\Services\ClinicService;

class ClinicController extends Controller
{
    public function __construct(
        private readonly ClinicService $clinicService
    ) {
    }

    public function getClinic(Request $request)
    {
        $clinic = $this->clinicService->get($request->user()->clinic_id);

        return response()->json($clinic);
    }

    public function updateSettings(Request $request)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'open_latency_ms' => 'required|integer|min:0',
        ]);

        $this->clinicService->updateSettings(
            $request->user()->clinic_id,
            $validated['open_latency_ms']
        );

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
