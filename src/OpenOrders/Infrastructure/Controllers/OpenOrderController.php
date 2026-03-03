<?php

namespace Src\OpenOrders\Infrastructure\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\OpenOrders\Application\Services\OpenOrderService;

class OpenOrderController extends Controller
{
    public function __construct(
        private readonly OpenOrderService $openOrderService
    ) {
    }

    public function index(Request $request)
    {
        $orders = $this->openOrderService->list(
            $request->user()->clinic_id,
            $request->input('status')
        );

        return response()->json($orders);
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-orders');

        $validated = $request->validate([
            'compartment_id' => 'required|string',
            'product_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'external_ref' => 'nullable|string',
        ]);

        $externalRef = $request->header('Idempotency-Key', $validated['external_ref'] ?? null);
        $user = $request->user();

        try {
            $result = $this->openOrderService->create(
                $user->clinic_id,
                $user->id,
                $validated['compartment_id'],
                $validated['product_id'],
                $validated['quantity'],
                $externalRef
            );

            return response()->json($result['order'], $result['created'] ? 201 : 200);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function confirmRead(Request $request, string $id)
    {
        $user = $request->user();
        $occurredAt = $request->input('occurred_at')
            ? Carbon::parse($request->input('occurred_at'))
            : null;

        $result = $this->openOrderService->confirmRead(
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
