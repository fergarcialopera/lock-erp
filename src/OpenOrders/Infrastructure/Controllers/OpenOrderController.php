<?php

namespace Src\OpenOrders\Infrastructure\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
