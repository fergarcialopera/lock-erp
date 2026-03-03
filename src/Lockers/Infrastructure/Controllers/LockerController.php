<?php

namespace Src\Lockers\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Lockers\Application\Services\LockerService;

class LockerController extends Controller
{
    public function __construct(
        private readonly LockerService $lockerService
    ) {
    }

    public function index(Request $request)
    {
        $lockers = $this->lockerService->list(
            $request->user()->clinic_id,
            $request->boolean('active_only', true)
        );

        return response()->json($lockers);
    }

    public function show(Request $request, string $id)
    {
        $locker = $this->lockerService->findWithCompartments($id, $request->user()->clinic_id);

        if (!$locker) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        return response()->json($locker);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
        ]);

        try {
            $locker = $this->lockerService->create($request->user()->clinic_id, $validated);
            return response()->json($locker, 201);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'code' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $locker = $this->lockerService->update($id, $request->user()->clinic_id, $validated);

            if (!$locker) {
                return response()->json(['error' => 'Locker not found'], 404);
            }

            return response()->json($locker);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $deactivated = $this->lockerService->deactivate($id, $request->user()->clinic_id);

        if (!$deactivated) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        return response()->json(['message' => 'Locker deactivated successfully']);
    }
}
