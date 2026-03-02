<?php

namespace Src\Identity\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-users');

        $clinicId = $request->user()->clinic_id;
        $query = DB::table('users')->where('clinic_id', $clinicId);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $users = $query->orderBy('name')->get()->map(fn ($u) => $this->excludePassword($u));

        return response()->json($users);
    }

    public function show(Request $request, string $id)
    {
        Gate::authorize('manage-users');

        $clinicId = $request->user()->clinic_id;
        $user = DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($this->excludePassword($user));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:ADMIN,RESPONSABLE,READONLY',
        ]);

        $clinicId = $request->user()->clinic_id;

        $exists = DB::table('users')
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'A user with this email already exists'], 422);
        }

        $id = Str::ulid()->toString();
        DB::table('users')->insert([
            'id' => $id,
            'clinic_id' => $clinicId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('id', $id)->first();

        return response()->json($this->excludePassword($user), 201);
    }

    public function update(Request $request, string $id)
    {
        Gate::authorize('manage-users');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:ADMIN,RESPONSABLE,READONLY',
            'is_active' => 'sometimes|boolean',
        ]);

        $clinicId = $request->user()->clinic_id;

        $user = DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $exists = DB::table('users')
                ->where('email', $validated['email'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json(['error' => 'A user with this email already exists'], 422);
            }
        }

        $updateData = [];
        foreach (['name', 'email', 'role', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }
        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }
        $updateData['updated_at'] = now();

        DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update($updateData);

        $user = DB::table('users')->where('id', $id)->first();

        return response()->json($this->excludePassword($user));
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-users');

        $clinicId = $request->user()->clinic_id;

        if ($id === $request->user()->id) {
            return response()->json(['error' => 'You cannot deactivate your own account'], 422);
        }

        $user = DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return response()->json(['message' => 'User deactivated successfully']);
    }

    private function excludePassword(object $user): object
    {
        $arr = (array) $user;
        unset($arr['password']);
        return (object) $arr;
    }
}
