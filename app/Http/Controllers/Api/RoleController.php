<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('id')->get();
        return response()->json($roles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200|unique:roles,name',
            'slug' => 'nullable|string|max:200|unique:roles,slug',
            'description' => 'nullable|string',
            'primary_desk' => 'nullable|string',
            'allowed_desks' => 'nullable|array',
            'assigned_modules' => 'nullable|array',
            'status' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $role = Role::create($validated);
        return response()->json($role, 201);
    }

    public function show($id): JsonResponse
    {
        $role = Role::findOrFail($id);
        return response()->json($role);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200|unique:roles,name,' . $id,
            'description' => 'nullable|string',
            'primary_desk' => 'nullable|string',
            'allowed_desks' => 'nullable|array',
            'assigned_modules' => 'nullable|array',
            'status' => 'nullable|boolean',
        ]);

        $role->update($validated);
        return response()->json($role);
    }

    public function destroy($id): JsonResponse
    {
        $role = Role::findOrFail($id);
        if ($role->is_system_role) {
            return response()->json(['error' => 'System roles cannot be deleted', 'status' => 403], 403);
        }
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }
}
