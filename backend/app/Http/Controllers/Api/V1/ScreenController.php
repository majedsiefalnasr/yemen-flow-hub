<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\Screen;
use App\Services\Authorization\PermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function index(Request $request)
    {
        if (! $this->permissionService->userHasCapability($request->user(), 'screen_permissions', 'VIEW')) {
            abort(403, 'You are not authorized to view the screen catalog.');
        }

        $screens = Screen::query()->orderBy('key')->get(['id', 'key', 'label']);

        return ApiResponse::success($screens, 'Screens retrieved.');
    }
}
