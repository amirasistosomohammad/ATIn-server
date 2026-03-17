<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users (admin only). Used for user management and accountability report dropdown.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('name');

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', $q)
                    ->orWhere('email', 'like', $q);
            });
        }

        $users = $query->get(['id', 'name', 'email', 'role', 'is_active', 'deactivation_remarks', 'section_unit', 'designation_position', 'email_verified_at', 'updated_at']);

        return response()->json(['data' => $users]);
    }

    /**
     * Update a user (admin only). Cannot change email (identity).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,user'],
            'section_unit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'deactivation_remarks' => ['sometimes', 'nullable', 'string', 'max:65535'],
        ]);

        $data = [];
        if (array_key_exists('name', $validated)) $data['name'] = $validated['name'];
        if (array_key_exists('role', $validated)) $data['role'] = $validated['role'];
        if (array_key_exists('section_unit', $validated)) $data['section_unit'] = $validated['section_unit'];
        if (array_key_exists('designation_position', $validated)) $data['designation_position'] = $validated['designation_position'];
        if (array_key_exists('is_active', $validated)) $data['is_active'] = (bool) $validated['is_active'];
        if (array_key_exists('deactivation_remarks', $validated)) $data['deactivation_remarks'] = $validated['deactivation_remarks'];
        if (! empty($data)) {
            $user->update($data);
        }

        return response()->json([
            'message' => 'User updated.',
            'user' => $user->fresh()->only(['id', 'name', 'email', 'role', 'is_active', 'deactivation_remarks', 'section_unit', 'designation_position', 'updated_at']),
        ]);
    }

    /**
     * Delete a user (admin only).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Do not allow deleting admin accounts via this endpoint.
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Admin accounts cannot be deleted.',
            ], 403);
        }

        // Extra safety: prevent an administrator from deleting their own account.
        if ($request->user() && $request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account while signed in.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted.',
        ]);
    }
}
