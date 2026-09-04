<?php

namespace App\Http\Responses;

/**
 * Kode mesin yang dikirim di `response_code`.
 *
 * SENGAJA bukan HTTP status. Lihat ADR-0016 di repo learn_mobile: `403` bisa
 * berarti "password masih default" (klien harus mengarahkan ke layar ganti
 * password) atau "kamu tidak berhak" (pesan generik) — dua hal yang perlakuannya
 * berlawanan. Kalau `response_code` cuma berisi "403", klien tidak bisa
 * membedakannya dan akan menjebak siswa di layar error tanpa jalan keluar.
 *
 * HTTP status tetap dikirim benar di header; envelope melengkapi, bukan
 * menggantikan.
 */
enum ApiCode: string
{
    case Success = 'success';
    case BadRequest = 'bad_request';
    case Unauthenticated = 'unauthenticated';
    case PasswordChangeRequired = 'password_change_required';
    case AccountInactive = 'account_inactive';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case PayloadTooLarge = 'payload_too_large';
    case ValidationFailed = 'validation_failed';
    case ClientTooOld = 'client_too_old';
    case TooManyRequests = 'too_many_requests';
    case ServerError = 'server_error';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Success => 200,
            self::BadRequest => 400,
            self::Unauthenticated => 401,
            self::PasswordChangeRequired,
            self::AccountInactive,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::Conflict => 409,
            self::PayloadTooLarge => 413,
            self::ValidationFailed => 422,
            self::ClientTooOld => 426,
            self::TooManyRequests => 429,
            self::ServerError => 500,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::Success => 'Berhasil.',
            self::BadRequest => 'Permintaan tidak valid.',
            self::Unauthenticated => 'Sesi kamu sudah berakhir. Silakan masuk lagi.',
            self::PasswordChangeRequired => 'Ganti password default kamu terlebih dahulu.',
            self::AccountInactive => 'Akun kamu dinonaktifkan. Hubungi pihak sekolah.',
            self::Forbidden => 'Kamu tidak punya akses ke data ini.',
            self::NotFound => 'Data tidak ditemukan.',
            self::Conflict => 'Data sudah berubah. Muat ulang halaman.',
            self::PayloadTooLarge => 'Ukuran data terlalu besar.',
            self::ValidationFailed => 'Data yang dikirim tidak valid.',
            self::ClientTooOld => 'Versi aplikasi kamu sudah terlalu lama. Perbarui dulu.',
            self::TooManyRequests => 'Terlalu banyak permintaan. Coba lagi sebentar.',
            self::ServerError => 'Terjadi kesalahan di server. Coba lagi nanti.',
        };
    }
}
