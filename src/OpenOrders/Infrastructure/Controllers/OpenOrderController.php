<?php

namespace Src\OpenOrders\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OpenOrderController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id;
        $query = DB::table('open_orders')->where('clinic_id', $clinicId);

        if ($request->has('status')) {
            $query->where('status', strtoupper($request->input('status')));
        }

        return response()->json($query->get());
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-orders');
        $user = $request->user();

        $validated = $request->validate([
            'compartment_id' => 'required|string',
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'external_ref' => 'nullable|string'
        ]);

        $externalRef = $request->header('Idempotency-Key', $validated['external_ref'] ?? null);

        // Idempotency check
        if ($externalRef) {
            $existing = DB::table('open_orders')
                ->where('clinic_id', $user->clinic_id)
                ->where('external_ref', $externalRef)
                ->first();

            if ($existing) {
                return response()->json($existing);
            }
        }

        return DB::transaction(function () use ($user, $validated, $externalRef) {
            // lock row
            $inventory = DB::table('compartment_inventories')
                ->where('clinic_id', $user->clinic_id)
                ->where('compartment_id', $validated['compartment_id'])
                ->where('product_id', $validated['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->qty_available < $validated['quantity']) {
                abort(400, 'Not enough inventory');
            }

            // Move stock
            DB::table('compartment_inventories')->where('id', $inventory->id)->update([
                'qty_available' => $inventory->qty_available - $validated['quantity'],
                'qty_reserved' => $inventory->qty_reserved + $validated['quantity'],
                'updated_at' => now(),
            ]);

            // Get locker_id
            $compartment = DB::table('compartments')->where('id', $validated['compartment_id'])->first();

            $orderId = Str::ulid()->toString();
            DB::table('open_orders')->insert([
                'id' => $orderId,
                'clinic_id' => $user->clinic_id,
                'requested_by_user_id' => $user->id,
                'locker_id' => $compartment->locker_id,
                'compartment_id' => $validated['compartment_id'],
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
                'status' => 'PENDING',
                'requested_at' => now(),
                'external_ref' => $externalRef,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'id' => Str::ulid()->toString(),
                'clinic_id' => $user->clinic_id,
                'actor_user_id' => $user->id,
                'actor_type' => 'USER',
                'action' => 'open_order_requested',
                'entity_type' => 'OpenOrder',
                'entity_id' => $orderId,
                'occurred_at' => now(),
                'created_at' => now(),
            ]);

            return response()->json(DB::table('open_orders')->where('id', $orderId)->first(), 201);
        });
    }

    public function confirmRead(Request $request, $id)
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $id, $request) {
            $order = DB::table('open_orders')
                ->where('clinic_id', $user->clinic_id)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                abort(404, 'Order not found');
            }

            if ($order->status === 'RETIRED') {
                return response()->json(['message' => 'Already confirmed read'], 200);
            }

            // Reduce reserved stock
            $inventory = DB::table('compartment_inventories')
                ->where('clinic_id', $user->clinic_id)
                ->where('compartment_id', $order->compartment_id)
                ->where('product_id', $order->product_id)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $newReserved = max(0, $inventory->qty_reserved - $order->quantity);
                DB::table('compartment_inventories')->where('id', $inventory->id)->update([
                    'qty_reserved' => $newReserved,
                    'updated_at' => now(),
                ]);
            }

            $occurredAt = $request->input('occurred_at') ?\Carbon\Carbon::parse($request->input('occurred_at')) : now();

            DB::table('open_orders')->where('id', $id)->update([
                'status' => 'RETIRED',
                'read_at' => $occurredAt,
                'updated_at' => now(),
            ]);

            DB::table('audit_logs')->insert([
                'id' => Str::ulid()->toString(),
                'clinic_id' => $user->clinic_id,
                'actor_user_id' => $user->id,
                'actor_type' => 'USER',
                'action' => 'open_order_read_confirmed',
                'entity_type' => 'OpenOrder',
                'entity_id' => $id,
                'occurred_at' => $occurredAt,
                'created_at' => now(),
            ]);

            return response()->json(['message' => 'Read confirmed']);
        });
    }
}
