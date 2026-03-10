<?php

namespace Src\Inventory\Infrastructure\Controllers;

use DomainException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Inventory\Application\Services\InventoryService;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {
    }

    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id;
        $compartmentId = $request->has('compartment_id') ? $request->input('compartment_id') : null;

        $items = $this->inventoryService->list($clinicId, $compartmentId);

        return response()->json($items);
    }

    public function adjust(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'compartment_id' => 'required|string',
            'product_id' => 'required|string',
            'qty_available' => 'required|integer|min:0',
        ]);

        $this->inventoryService->adjust(
            $request->user()->clinic_id,
            $request->user()->id,
            $validated['compartment_id'],
            $validated['product_id'],
            $validated['qty_available']
        );

        return response()->json(['message' => 'Inventory adjusted']);
    }

    public function add(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'compartment_id' => 'required|string',
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $inventory = $this->inventoryService->addUnits(
                $request->user()->clinic_id,
                $request->user()->id,
                $validated['compartment_id'],
                $validated['product_id'],
                $validated['quantity']
            );
        } catch (DomainException $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                str_contains($e->getMessage(), 'Stock insuficiente') ? 400 : 404
            );
        }

        return response()->json([
            'message' => 'Inventory units added',
            'compartment_inventory' => $inventory,
        ]);
    }

    public function remove(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'compartment_id' => 'required|string',
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->inventoryService->removeUnits(
                $request->user()->clinic_id,
                $request->user()->id,
                $validated['compartment_id'],
                $validated['product_id'],
                $validated['quantity']
            );
        } catch (DomainException $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                str_contains($e->getMessage(), 'Stock insuficiente') ? 400 : 404
            );
        }

        return response()->json([
            'message' => 'Inventory units removed',
            'compartment_inventory' => $result['inventory'],
            'dispense' => $result['dispense'],
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        try {
            $this->inventoryService->deleteEntry($request->user()->clinic_id, $request->user()->id, $id);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json(['message' => 'Inventory entry deleted']);
    }
}
