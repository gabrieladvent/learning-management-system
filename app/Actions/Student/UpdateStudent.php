<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateStudent
{
    /**
     * Update Student dan (kalau diisi) password User-nya.
     *
     * Password hanya di-update kalau `password` ada DAN tidak kosong — sehingga
     * form edit yang tidak menyentuh field password tidak mengganggu kredensial.
     */
    public function handle(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $newPassword = trim((string) ($data['password'] ?? ''));

            unset($data['password']);

            $student->fill($data)->save();

            if ($student->user) {
                $student->user->forceFill([
                    'name' => $data['full_name'] ?? $student->user->name,
                    'is_active' => $data['is_active'] ?? $student->user->is_active,
                ])->save();

                if ($newPassword !== '') {
                    $student->user->forceFill([
                        'password' => Hash::make($newPassword),
                        'password_changed_at' => now(),
                    ])->save();
                }
            }

            return $student->refresh();
        });
    }
}
