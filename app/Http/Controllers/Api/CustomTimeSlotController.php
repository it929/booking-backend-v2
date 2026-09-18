<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomTimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomTimeSlotController extends Controller
{
    public function index(): JsonResponse
    {
        $slots = CustomTimeSlot::orderBy('id')->get();
        return response()->json($slots);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'code' => 'nullable|string|max:100',
        ]);

        $nextId = (CustomTimeSlot::max('id') ?? 0) + 1;
        $slot = CustomTimeSlot::create([
            'code' => $validated['code'] ?? ('slot-' . $nextId),
            'label' => $validated['label'],
            'status' => true,
        ]);

        return response()->json($slot, 201);
    }

    public function destroy($id): JsonResponse
    {
        $slot = CustomTimeSlot::where('id', $id)->orWhere('code', $id)->firstOrFail();
        $slot->delete();
        return response()->json(['message' => 'Time slot removed successfully']);
    }
}
