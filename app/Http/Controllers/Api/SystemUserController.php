<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SystemUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with('role')->orderBy('id')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'desk' => $u->desk,
                'role' => $u->role ? $u->role->name : 'Staff',
                'role_id' => $u->role_id,
                'status' => $u->status,
                'created_at' => $u->created_at,
            ];
        });

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string',
            'role' => 'nullable',
            'role_id' => 'nullable',
            'desk' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        $roleId = null;
        $desk = $validated['desk'] ?? null;

        if (!empty($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            if ($role) {
                $roleId = $role->id;
                if (empty($desk)) {
                    $desk = $role->primary_desk;
                }
            }
        } elseif (!empty($validated['role'])) {
            $role = Role::where('name', $validated['role'])->orWhere('slug', $validated['role'])->first();
            if ($role) {
                $roleId = $role->id;
                if (empty($desk)) {
                    $desk = $role->primary_desk;
                }
            }
        }

        if (empty($desk)) {
            $desk = 'helpdesk';
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role_id' => $roleId,
            'desk' => $desk,
            'status' => $validated['status'] ?? true,
        ]);

        $user->load('role');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'desk' => $user->desk,
            'role' => $user->role ? $user->role->name : 'Staff',
            'role_id' => $user->role_id,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $user = User::with('role')->findOrFail($id);
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'desk' => $user->desk,
            'role' => $user->role ? $user->role->name : 'Staff',
            'role_id' => $user->role_id,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string',
            'role_id' => 'nullable',
            'role' => 'nullable',
            'desk' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['role']) && !isset($validated['role_id'])) {
            $role = Role::where('name', $validated['role'])->orWhere('slug', $validated['role'])->first();
            if ($role) $validated['role_id'] = $role->id;
            unset($validated['role']);
        }

        $user->update($validated);
        $user->load('role');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'desk' => $user->desk,
            'role' => $user->role ? $user->role->name : 'Staff',
            'role_id' => $user->role_id,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
}
