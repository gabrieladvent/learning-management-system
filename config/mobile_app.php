<?php

/*
|--------------------------------------------------------------------------
| Aplikasi mobile siswa
|--------------------------------------------------------------------------
|
| Dipakai middleware EnsureClientSupported dan endpoint GET /api/v1/app-config.
| Lihat ADR-0013 di repo learn_mobile.
|
| Semua nilai lewat env supaya bisa dinaikkan tanpa deploy ulang kode. Menaikkan
| min_version MENGUNCI siswa yang belum memperbarui — ADR-0013 membatasi
| pemakaiannya pada bug yang merusak nilai dan celah keamanan, dan mewajibkan
| pengecekan sebaran versi aktif sebelum menaikkannya. JANGAN dinaikkan
| menjelang jadwal ujian.
|
*/

return [
    /*
     * Versi terendah yang masih dilayani, format "1.4.0" (tanpa nomor build).
     * Kosong = tidak ada batas; ini nilai bawaan yang disengaja, supaya
     * pemasangan baru tidak sengaja mengunci siapa pun.
     */
    'min_version' => env('MOBILE_MIN_VERSION'),

    /*
     * Versi terbaru yang ada di toko. Hanya untuk banner "ada pembaruan" yang
     * bisa ditutup siswa — tidak pernah mengunci apa pun.
     */
    'latest_version' => env('MOBILE_LATEST_VERSION'),

    /*
     * Tautan toko per platform. Server memilihkan satu berdasarkan header
     * X-Client-Platform, jadi aplikasi tidak perlu menyimpan ID-nya sendiri.
     */
    'store_url' => [
        'android' => env('MOBILE_STORE_URL_ANDROID'),
        'ios' => env('MOBILE_STORE_URL_IOS'),
    ],
];
