<?php

namespace App\Support;

use Illuminate\Http\Request;

final class MobileAppVersion
{
    public static function normalize(mixed $version): ?string
    {
        if (! is_string($version)) {
            return null;
        }

        $release = trim(explode('+', $version, 2)[0]);

        return preg_match('/^\d+(\.\d+)*$/', $release) === 1 ? $release : null;
    }

    public static function storeUrlFor(Request $request): ?string
    {
        $platform = strtolower((string) $request->header('X-Client-Platform'));
        
        $key = $platform === 'ios' ? 'ios' : 'android';

        $url = config("mobile_app.store_url.{$key}");

        return is_string($url) && $url !== '' ? $url : null;
    }
}
