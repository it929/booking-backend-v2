<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = AppSetting::all()->pluck('value', 'key');
        return response()->json($settings);
    }

    public function show($key): JsonResponse
    {
        $setting = AppSetting::where('key', $key)->first();
        return response()->json($setting ? $setting->value : null);
    }

    public function store(Request $request): JsonResponse
    {
        $key = $request->input('key');
        $value = $request->input('value');

        if (empty($key)) {
            return response()->json(['error' => 'Key is required', 'status' => 400], 400);
        }

        $setting = AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        return response()->json($setting);
    }
}
