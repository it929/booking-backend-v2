<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Doctor::preloadActiveBookings();
        $departments = Department::with(['doctors.schedules'])->withCount('doctors')->orderBy('name')->get();
        if (!$request->user('sanctum')) {
            $departments->each(function ($dept) {
                if ($dept->relationLoaded('doctors')) {
                    $dept->doctors->each->maskForPublic();
                }
            });
        }
        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:100|unique:departments,code',
            'description' => 'nullable|string',
            'icon_name' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:200',
            'status' => 'nullable|boolean',
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
            $count = 1;
            while (Department::where('code', $validated['code'])->exists()) {
                $validated['code'] = Str::slug($validated['name']) . '-' . $count++;
            }
        }

        $department = Department::create($validated);
        return response()->json($department, 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $department = Department::with(['doctors.schedules'])->where('id', $id)->orWhere('code', $id)->firstOrFail();
        if (!$request->user('sanctum') && $department->relationLoaded('doctors')) {
            $department->doctors->each->maskForPublic();
        }
        return response()->json($department);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $department = Department::where('id', $id)->orWhere('code', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'code' => 'nullable|string|max:100|unique:departments,code,' . $department->id,
            'description' => 'nullable|string',
            'icon_name' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:200',
            'status' => 'nullable|boolean',
        ]);

        $department->update($validated);
        return response()->json($department);
    }

    public function destroy($id): JsonResponse
    {
        $department = Department::where('id', $id)->orWhere('code', $id)->firstOrFail();
        $department->delete();
        return response()->json(['message' => 'Department deleted successfully']);
    }
}
