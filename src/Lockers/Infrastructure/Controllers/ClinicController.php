<?php

namespace Src\Lockers\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClinicController extends Controller
{
    public function getClinic(Request $request)
    {
        $clinicId = $request->user()->clinic_id;
        $clinic = DB::table('clinics')->where('id', $clinicId)->first();

        return response()->json($clinic);
    }

    public function updateSettings(Request $request)
    {
        Gate::authorize('manage-settings');

        $validated = $request->validate([
            'open_latency_ms' => 'required|integer|min:0'
        ]);

        $clinicId = $request->user()->clinic_id;
        $clinic = DB::table('clinics')->where('id', $clinicId)->first();

        $settings = json_decode($clinic->settings, true) ?? [];
        $settings['open_latency_ms'] = $validated['open_latency_ms'];

        DB::table('clinics')->where('id', $clinicId)->update([
            'settings' => json_encode($settings)
        ]);

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
