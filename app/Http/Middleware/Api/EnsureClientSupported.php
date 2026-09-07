<?php

namespace App\Http\Middleware\Api;

use App\Http\Responses\ApiCode;
use App\Http\Responses\ApiResponse;
use App\Support\MobileAppVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientSupported
{
    public function handle(Request $request, Closure $next): Response
    {
        $minimum = MobileAppVersion::normalize(config('mobile_app.min_version'));
        $client = MobileAppVersion::normalize($request->header('X-Client-Version'));

        if ($minimum === null || $client === null) {
            return $next($request);
        }

        if (version_compare($client, $minimum, '<')) {
            return ApiResponse::error(ApiCode::ClientTooOld, data: [
                'min_version' => $minimum,
                'store_url' => MobileAppVersion::storeUrlFor($request),
            ]);
        }

        return $next($request);
    }
}
