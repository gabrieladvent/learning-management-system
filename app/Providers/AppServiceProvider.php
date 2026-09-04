<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Support\CauserResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Default activitylog resolver hanya cek guard `web`. Sistem ini punya guard
        // `student` (NIS + birth_date) yang tidak overlap dengan web → kita resolve
        // dari guard mana pun yang sedang authenticated.
        //
        // `student-api` (token Sanctum, aplikasi mobile) WAJIB ikut di sini.
        // Tanpa itu, activity dari aplikasi tercatat tanpa causer — dan karena
        // `material_download` dipakai sebagai proxy completion (docs/11 §7.1),
        // progres belajar siswa yang memakai aplikasi akan hilang dari laporan
        // guru tanpa error apa pun.
        app(CauserResolver::class)->resolveUsing(
            fn () => Auth::guard('web')->user()
                ?? Auth::guard('student')->user()
                ?? Auth::guard('student-api')->user()
        );
    }
}
