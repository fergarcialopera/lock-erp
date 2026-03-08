<?php

namespace Src\Lockers\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Lockers\Application\Services\CompartmentService;

class CompartmentController extends Controller
{
    public function __construct(
        private readonly CompartmentService $compartmentService
    ) {
    }

    public function index(Request $request)
    {
        $compartments = $this->compartmentService->list(
            $request->user()->clinic_id,
            $request->input('locker_id'),
            $request->boolean('active_only', true)
        );

        return response()->json($compartments);
    }

    public function indexByLocker(Request $request, string $lockerId)
    {
        $compartments = $this->compartmentService->listByLocker(
            $lockerId,
            $request->user()->clinic_id,
            $request->boolean('active_only', true)
        );

        if ($compartments === null) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        return response()->json($compartments);
    }

    public function show(Request $request, string $id)
    {
        $compartment = $this->compartmentService->find($id, $request->user()->clinic_id);

        if (!$compartment) {
            return response()->json(['error' => 'Compartment not found'], 404);
        }

        return response()->json($compartment);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'locker_id' => 'required|string',
            'code' => 'required|string|max:255',
            'status' => 'nullable|in:AVAILABLE,MAINTENANCE',
        ]);

        try {
            $compartment = $this->compartmentService->create($request->user()->clinic_id, $request->user()->id, $validated);
            return response()->json($compartment, 201);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'code' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:AVAILABLE,MAINTENANCE',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $compartment = $this->compartmentService->update($id, $request->user()->clinic_id, $request->user()->id, $validated);

            if (!$compartment) {
                return response()->json(['error' => 'Compartment not found'], 404);
            }

            return response()->json($compartment);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $deactivated = $this->compartmentService->deactivate($id, $request->user()->clinic_id, $request->user()->id);

        if (!$deactivated) {
            return response()->json(['error' => 'Compartment not found'], 404);
        }

        return response()->json(['message' => 'Compartment deactivated successfully']);
    }
}
