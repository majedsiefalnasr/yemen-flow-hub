<?php

namespace App\Http\Controllers\Api;

use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ReportPresetsController extends Controller
{
    public function index(Request $request)
    {
        $presets = $this->presets($request);

        return ApiResponse::success($presets, 'Report presets retrieved.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|string|max:64',
            'name' => 'required|string|max:50',
            'filter' => 'required|array',
            'createdAt' => 'required|string|max:30',
        ]);

        $user = $request->user();
        $prefs = $user->user_preferences ?? [];
        $presets = is_array($prefs['report_presets'] ?? null) ? $prefs['report_presets'] : [];

        $presets[] = $request->only(['id', 'name', 'filter', 'createdAt']);
        $prefs['report_presets'] = $presets;

        $user->user_preferences = $prefs;
        $user->save();

        return ApiResponse::success($presets, 'Preset saved.');
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $prefs = $user->user_preferences ?? [];
        $presets = is_array($prefs['report_presets'] ?? null) ? $prefs['report_presets'] : [];

        $prefs['report_presets'] = array_values(
            array_filter($presets, fn ($p) => ($p['id'] ?? '') !== $id)
        );

        $user->user_preferences = $prefs;
        $user->save();

        return ApiResponse::success($prefs['report_presets'], 'Preset deleted.');
    }

    private function presets(Request $request): array
    {
        $prefs = $request->user()->user_preferences ?? [];

        return is_array($prefs['report_presets'] ?? null) ? $prefs['report_presets'] : [];
    }
}
