<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Models\Screen;
use App\Support\ApiResponse;

class ScreenController extends Controller
{
    public function index()
    {
        $screens = Screen::query()->orderBy('key')->get(['id', 'key', 'label']);

        return ApiResponse::success($screens, 'Screens retrieved.');
    }
}
