<?php

namespace Src\Identity\Infrastructure\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Src\Identity\Application\Services\UserService;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {
    }

    public function index(Request $request)
    {
        // Gate::authorize('manage-users');

        try {
            $users = $this->userService->list(
                $request->user()->clinic_id,
                $request->boolean('active_only', true)
            );
            return response()->json($users);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, string $id)
    {
        Gate::authorize('manage-users');

        $user = $this->userService->find($id, $request->user()->clinic_id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($user);
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

        try {
            $user = $this->userService->create($request->user()->clinic_id, $validated);
            return response()->json($user, 201);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
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

        try {
            $user = $this->userService->update($id, $request->user()->clinic_id, $validated);

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            return response()->json($user);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, string $id)
    {
        Gate::authorize('manage-users');

        if ($id === $request->user()->id) {
            return response()->json(['error' => 'You cannot deactivate your own account'], 422);
        }

        $deactivated = $this->userService->deactivate($id, $request->user()->clinic_id);

        if (!$deactivated) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json(['message' => 'User deactivated successfully']);
    }
}
