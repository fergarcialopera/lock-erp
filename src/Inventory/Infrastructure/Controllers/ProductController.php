<?php

namespace Src\Inventory\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id;
        $query = DB::table('products')->where('clinic_id', $clinicId);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function show(Request $request, string $id)
    {
        $clinicId = $request->user()->clinic_id;
        $product = DB::table('products')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

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

        $clinicId = $request->user()->clinic_id;

        $exists = DB::table('products')
            ->where('clinic_id', $clinicId)
            ->where('sku', $validated['sku'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'A product with this SKU already exists'], 422);
        }

        $id = Str::ulid()->toString();
        DB::table('products')->insert([
            'id' => $id,
            'clinic_id' => $clinicId,
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'barcode' => $validated['barcode'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = DB::table('products')->where('id', $id)->first();

        return response()->json($product, 201);
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

        $clinicId = $request->user()->clinic_id;

        $product = DB::table('products')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        if (isset($validated['sku']) && $validated['sku'] !== $product->sku) {
            $exists = DB::table('products')
                ->where('clinic_id', $clinicId)
                ->where('sku', $validated['sku'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'A product with this SKU already exists'], 422);
            }
        }

        $updateData = array_intersect_key($validated, array_flip(['sku', 'name', 'barcode', 'is_active']));
        $updateData['updated_at'] = now();

        DB::table('products')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update($updateData);

        $product = DB::table('products')->where('id', $id)->first();

        return response()->json($product);
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $clinicId = $request->user()->clinic_id;

        $product = DB::table('products')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        DB::table('products')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json(['message' => 'Product deactivated successfully']);
    }
}
