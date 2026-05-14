<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *   title="Yemen Flow Hub API",
 *   version="1.0.0",
 *   description="Internal regulatory workflow API for CBY import financing requests."
 * )
 *
 * @OA\Server(
 *   url="/",
 *   description="Current API server"
 * )
 *
 * @OA\SecurityScheme(
 *   securityScheme="sanctumCookie",
 *   type="apiKey",
 *   in="cookie",
 *   name="laravel_session",
 *   description="Sanctum stateful cookie auth for SPA"
 * )
 *
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="Token",
 *   description="Sanctum personal access token"
 * )
 */
abstract class Controller extends BaseController
{
}
