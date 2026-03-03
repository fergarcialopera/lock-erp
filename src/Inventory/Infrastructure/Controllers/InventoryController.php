<?php

namespace Src\Inventory\Infrastructure\Controllers;

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
            $validated['compartment_id'],
            $validated['product_id'],
            $validated['qty_available']
        );

        return response()->json(['message' => 'Inventory adjusted']);
    }
}
