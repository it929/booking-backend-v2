<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($request->email));
        $user = User::with('role')->where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials',
                'detail' => 'The email or password provided is incorrect.',
                'status' => 401,
            ], 401);
        }

        if (!$user->status) {
            return response()->json([
                'error' => 'Account deactivated',
                'detail' => 'Your staff account is currently inactive. Contact system administrator.',
                'status' => 403,
            ], 403);
        }

        // Generate Sanctum access token
        $token = $user->createToken('staff-access-token')->plainTextToken;

        $roleData = $user->role;
        $roleName = $roleData ? $roleData->name : 'Staff';
        $allowedDesks = $roleData && $roleData->allowed_desks ? $roleData->allowed_desks : ['helpdesk'];
        $assignedModules = $this->resolveAssignedModules($roleData, $user);
        $isSuperuser = $roleData && ($roleData->slug === 'super-admin' || $roleData->name === 'Super Administrator');

        return response()->json([
            'token' => $token,
            'access' => $token,
            'refresh' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'desk' => $user->desk,
                'role' => $roleName,
                'role_id' => $user->role_id,
                'role_data' => $roleData,
                'allowed_desks' => $allowedDesks,
                'assigned_modules' => $assignedModules,
                'is_superuser' => $isSuperuser,
                'is_staff' => true,
            ],
            'message' => 'Staff login successful',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');
        $roleData = $user->role;
        $roleName = $roleData ? $roleData->name : 'Staff';
        $allowedDesks = $roleData && $roleData->allowed_desks ? $roleData->allowed_desks : ['helpdesk'];
        $assignedModules = $this->resolveAssignedModules($roleData, $user);
        $isSuperuser = $roleData && ($roleData->slug === 'super-admin' || $roleData->name === 'Super Administrator');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'desk' => $user->desk,
                'role' => $roleName,
                'role_id' => $user->role_id,
                'role_data' => $roleData,
                'allowed_desks' => $allowedDesks,
                'assigned_modules' => $assignedModules,
                'is_superuser' => $isSuperuser,
                'is_staff' => true,
            ]
        ]);
    }

    private function resolveAssignedModules($role, $user): array
    {
        if ($role && !empty($role->assigned_modules) && is_array($role->assigned_modules)) {
            return $role->assigned_modules;
        }

        $roleSlug = $role ? strtolower($role->slug ?? '') : '';
        $roleName = $role ? strtolower($role->name ?? '') : '';
        $primaryDesk = strtolower($role->primary_desk ?? $user->desk ?? '');

        if ($roleSlug === 'super-admin' || str_contains($roleName, 'admin') || str_contains($primaryDesk, 'admin')) {
            return [
                'clinical-triage',
                'hmo-insurance',
                'finance-billing',
                'clinic-registry',
                'administration',
                'executive-intelligence',
                'hospital-settings',
            ];
        }

        if (str_contains($roleSlug, 'cash') || str_contains($roleName, 'cash') || str_contains($primaryDesk, 'cash') || str_contains($roleName, 'billing')) {
            return ['finance-billing'];
        }

        if (str_contains($roleSlug, 'hmo') || str_contains($roleName, 'hmo') || str_contains($primaryDesk, 'hmo')) {
            return ['hmo-insurance'];
        }

        if (str_contains($roleSlug, 'monitor') || str_contains($roleName, 'monitor') || str_contains($primaryDesk, 'monitor')) {
            return ['clinical-triage'];
        }

        if (str_contains($roleSlug, 'analytics') || str_contains($roleName, 'analytics') || str_contains($primaryDesk, 'analytics')) {
            return ['executive-intelligence'];
        }

        // Helpdesk / Reception / General Desk
        return ['clinical-triage', 'clinic-registry'];
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $newToken = $user->createToken('staff-access-token')->plainTextToken;

        return response()->json([
            'access' => $newToken,
            'token' => $newToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
