<?php

namespace Src\Dispenses\Infrastructure\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Src\Dispenses\Application\Services\DispenseService;

/**
 * API de dispensaciones: listado y detalle de retiradas desde locker listos para pintar en frontend.
 * Rutas: GET /dispenses, GET /dispenses/{id}, POST /dispenses/{id}/confirm-read
 */
class DispenseController extends Controller
{
    public function __construct(
        private readonly DispenseService $dispenseService
    ) {
    }

    public function index(Request $request)
    {
        $dispenses = $this->dispenseService->list(
            $request->user()->clinic_id,
            $request->input('status')
        );

        return response()->json($dispenses);
    }

    public function show(Request $request, string $id)
    {
        $dispense = $this->dispenseService->getDetail($id, $request->user()->clinic_id);

        if ($dispense === null) {
            return response()->json(['error' => 'Dispense not found'], 404);
        }

        return response()->json($dispense);
    }

    public function confirmRead(Request $request, string $id)
    {
        $user = $request->user();
        $occurredAt = $request->input('occurred_at')
            ? Carbon::parse($request->input('occurred_at'))
            : null;

        $result = $this->dispenseService->confirmRead(
            $id,
            $user->clinic_id,
            $user->id,
            $occurredAt
        );

        if (!$result['found']) {
            return response()->json(['error' => $result['message']], 404);
        }

        return response()->json(['message' => $result['message']]);
    }
}
