<?php

namespace Src\Inventory\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id;
        $query = DB::table('compartment_inventories')
            ->where('clinic_id', $clinicId);

        if ($request->has('compartment_id')) {
            $query->where('compartment_id', $request->input('compartment_id'));
        }

        return response()->json($query->get());
    }

    public function adjust(Request $request)
    {
        Gate::authorize('manage-inventory');
        $user = $request->user();

        $validated = $request->validate([
            'compartment_id' => 'required|string',
            'product_id' => 'required|string',
            'qty_available' => 'required|integer|min:0'
        ]);

        DB::table('compartment_inventories')->updateOrInsert(
        [
            'clinic_id' => $user->clinic_id,
            'compartment_id' => $validated['compartment_id'],
            'product_id' => $validated['product_id'],
        ],
        [
            'id' => \Illuminate\Support\Str::ulid()->toString(),
            'qty_available' => $validated['qty_available'],
            'updated_at' => now(),
        ]
        );

        return response()->json(['message' => 'Inventory adjusted']);
    }
}
