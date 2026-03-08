<?php

namespace Src\OpenOrders\Infrastructure\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Src\OpenOrders\Application\Services\OpenOrderService;

/**
 * API orientada a casos de uso: listado y detalle de órdenes listos para pintar en frontend.
 * Rutas: GET /orders, GET /orders/{id}, POST /orders/{id}/confirm-read
 */
class OrdersController extends Controller
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

    public function show(Request $request, string $id)
    {
        $order = $this->openOrderService->getDetail($id, $request->user()->clinic_id);

        if ($order === null) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json($order);
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
