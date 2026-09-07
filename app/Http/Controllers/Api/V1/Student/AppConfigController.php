<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\MobileAppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'min_version' => MobileAppVersion::normalize(config('mobile_app.min_version')),
            'latest_version' => MobileAppVersion::normalize(config('mobile_app.latest_version')),
            'store_url' => MobileAppVersion::storeUrlFor($request),
        ]);
    }
}
