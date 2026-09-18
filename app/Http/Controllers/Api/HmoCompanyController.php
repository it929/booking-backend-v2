<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HmoCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HmoCompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $hmos = HmoCompany::orderBy('name')->get();
        return response()->json($hmos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:100|unique:hmo_companies,code',
            'policy_code' => 'nullable|string|max:50',
            'email' => 'required|email',
            'phone' => 'required|string',
            'contact_person' => 'required|string',
            'status' => 'nullable|boolean',
        ]);

        if (empty($validated['code'])) {
            $nextId = (HmoCompany::max('id') ?? 0) + 1;
            $validated['code'] = 'hmo-' . $nextId;
        }

        $hmo = HmoCompany::create($validated);
        return response()->json($hmo, 201);
    }

    public function show($id): JsonResponse
    {
        $hmo = HmoCompany::where('id', $id)->orWhere('code', $id)->firstOrFail();
        return response()->json($hmo);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $hmo = HmoCompany::where('id', $id)->orWhere('code', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'code' => 'nullable|string|max:100|unique:hmo_companies,code,' . $hmo->id,
            'policy_code' => 'nullable|string|max:50',
            'email' => 'sometimes|required|email',
            'phone' => 'sometimes|required|string',
            'contact_person' => 'sometimes|required|string',
            'status' => 'nullable|boolean',
        ]);

        $hmo->update($validated);
        return response()->json($hmo);
    }

    public function destroy($id): JsonResponse
    {
        $hmo = HmoCompany::where('id', $id)->orWhere('code', $id)->firstOrFail();
        $hmo->delete();
        return response()->json(['message' => 'HMO Company deleted successfully']);
    }
}
