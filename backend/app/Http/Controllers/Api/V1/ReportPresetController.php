<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportPresetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $presets = $this->presets($request);

        return ApiResponse::success($presets, 'Report presets retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|max:64',
            'name' => 'required|string|max:50',
            'filter' => 'required|array',
            'createdAt' => 'required|string|max:30',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $user = $request->user();
        $prefs = $user->user_preferences ?? [];
        $presets = is_array($prefs['report_presets'] ?? null) ? $prefs['report_presets'] : [];

        $presets[] = $request->only(['id', 'name', 'filter', 'createdAt']);
        $prefs['report_presets'] = $presets;

        $user->user_preferences = $prefs;
        $user->save();

        return ApiResponse::success($presets, 'Preset saved.');
    }

    public function destroy(Request $request, string $id): JsonResponse
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presets(Request $request): array
    {
        $prefs = $request->user()->user_preferences ?? [];

        return is_array($prefs['report_presets'] ?? null) ? $prefs['report_presets'] : [];
    }

    /**
     * @param  array<string, array<int, string>>  $fields
     */
    private function validationError(array $fields): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'Validation failed.',
                'fields' => (object) $fields,
                'request_id' => request()->header('X-Request-ID'),
            ],
        ], 422);
    }
}
