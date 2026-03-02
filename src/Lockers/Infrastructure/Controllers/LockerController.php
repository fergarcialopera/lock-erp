<?php

namespace Src\Lockers\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class LockerController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id;
        $query = DB::table('lockers')->where('clinic_id', $clinicId);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('code')->get());
    }

    public function show(Request $request, string $id)
    {
        $clinicId = $request->user()->clinic_id;
        $locker = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$locker) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        $compartments = DB::table('compartments')
            ->where('locker_id', $id)
            ->orderBy('code')
            ->get();

        $locker = (array) $locker;
        $locker['compartments'] = $compartments;

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

        $clinicId = $request->user()->clinic_id;

        $exists = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'A locker with this code already exists'], 422);
        }

        $id = Str::ulid()->toString();
        DB::table('lockers')->insert([
            'id' => $id,
            'clinic_id' => $clinicId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locker = DB::table('lockers')->where('id', $id)->first();

        return response()->json($locker, 201);
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

        $clinicId = $request->user()->clinic_id;

        $locker = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$locker) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        if (isset($validated['code']) && $validated['code'] !== $locker->code) {
            $exists = DB::table('lockers')
                ->where('clinic_id', $clinicId)
                ->where('code', $validated['code'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'A locker with this code already exists'], 422);
            }
        }

        $updateData = array_intersect_key($validated, array_flip(['code', 'name', 'location', 'is_active']));
        $updateData['updated_at'] = now();

        DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update($updateData);

        $locker = DB::table('lockers')->where('id', $id)->first();

        return response()->json($locker);
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $clinicId = $request->user()->clinic_id;

        $locker = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$locker) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json(['message' => 'Locker deactivated successfully']);
    }
}
