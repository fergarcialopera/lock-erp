<?php

namespace Src\Lockers\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CompartmentController extends Controller
{
    public function index(Request $request)
    {
        $clinicId = $request->user()->clinic_id;

        $query = DB::table('compartments')
            ->join('lockers', 'compartments.locker_id', '=', 'lockers.id')
            ->where('lockers.clinic_id', $clinicId)
            ->select('compartments.*');

        if ($request->has('locker_id')) {
            $query->where('compartments.locker_id', $request->input('locker_id'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('compartments.is_active', true);
        }

        return response()->json($query->orderBy('compartments.code')->get());
    }

    public function indexByLocker(Request $request, string $lockerId)
    {
        $clinicId = $request->user()->clinic_id;

        $locker = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $lockerId)
            ->first();

        if (!$locker) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        $query = DB::table('compartments')
            ->where('locker_id', $lockerId);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('code')->get());
    }

    public function show(Request $request, string $id)
    {
        $clinicId = $request->user()->clinic_id;

        $compartment = DB::table('compartments')
            ->join('lockers', 'compartments.locker_id', '=', 'lockers.id')
            ->where('lockers.clinic_id', $clinicId)
            ->where('compartments.id', $id)
            ->select('compartments.*')
            ->first();

        if (!$compartment) {
            return response()->json(['error' => 'Compartment not found'], 404);
        }

        return response()->json($compartment);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'locker_id' => 'required|string',
            'code' => 'required|string|max:255',
            'status' => 'nullable|in:AVAILABLE,MAINTENANCE',
        ]);

        $clinicId = $request->user()->clinic_id;

        $locker = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $validated['locker_id'])
            ->first();

        if (!$locker) {
            return response()->json(['error' => 'Locker not found'], 404);
        }

        $exists = DB::table('compartments')
            ->where('locker_id', $validated['locker_id'])
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'A compartment with this code already exists in this locker'], 422);
        }

        $id = Str::ulid()->toString();
        DB::table('compartments')->insert([
            'id' => $id,
            'locker_id' => $validated['locker_id'],
            'code' => $validated['code'],
            'status' => $validated['status'] ?? 'AVAILABLE',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $compartment = DB::table('compartments')->where('id', $id)->first();

        return response()->json($compartment, 201);
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $validated = $request->validate([
            'code' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:AVAILABLE,MAINTENANCE',
            'is_active' => 'sometimes|boolean',
        ]);

        $clinicId = $request->user()->clinic_id;

        $compartment = DB::table('compartments')
            ->join('lockers', 'compartments.locker_id', '=', 'lockers.id')
            ->where('lockers.clinic_id', $clinicId)
            ->where('compartments.id', $id)
            ->select('compartments.*')
            ->first();

        if (!$compartment) {
            return response()->json(['error' => 'Compartment not found'], 404);
        }

        if (isset($validated['code']) && $validated['code'] !== $compartment->code) {
            $exists = DB::table('compartments')
                ->where('locker_id', $compartment->locker_id)
                ->where('code', $validated['code'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'A compartment with this code already exists in this locker'], 422);
            }
        }

        $updateData = array_intersect_key($validated, array_flip(['code', 'status', 'is_active']));
        $updateData['updated_at'] = now();

        DB::table('compartments')->where('id', $id)->update($updateData);

        $compartment = DB::table('compartments')->where('id', $id)->first();

        return response()->json($compartment);
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-inventory');

        $clinicId = $request->user()->clinic_id;

        $compartment = DB::table('compartments')
            ->join('lockers', 'compartments.locker_id', '=', 'lockers.id')
            ->where('lockers.clinic_id', $clinicId)
            ->where('compartments.id', $id)
            ->select('compartments.id')
            ->first();

        if (!$compartment) {
            return response()->json(['error' => 'Compartment not found'], 404);
        }

        DB::table('compartments')
            ->where('id', $id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json(['message' => 'Compartment deactivated successfully']);
    }
}
