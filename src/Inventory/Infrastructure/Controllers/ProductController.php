<?php

namespace Src\Inventory\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Inventory\Application\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    public function index(Request $request)
    {
        $products = $this->productService->list(
            $request->user()->clinic_id,
            $request->boolean('active_only', true)
        );

        return response()->json($products);
    }

    public function show(Request $request, string $id)
    {
        $product = $this->productService->find($id, $request->user()->clinic_id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
        ]);

        try {
            $product = $this->productService->create($request->user()->clinic_id, $validated);
            return response()->json($product, 201);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'sku' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $product = $this->productService->update($id, $request->user()->clinic_id, $validated);

            if (!$product) {
                return response()->json(['error' => 'Product not found'], 404);
            }

            return response()->json($product);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $deactivated = $this->productService->deactivate($id, $request->user()->clinic_id);

        if (!$deactivated) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json(['message' => 'Product deactivated successfully']);
    }
}
