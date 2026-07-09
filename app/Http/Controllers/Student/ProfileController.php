<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentProgress;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(GetStudentProgress $progress): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return Inertia::render('Profile/StudentProfile', [
            'progress' => $progress->handle($student),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $user = $student->user;

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($user === null || ! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ])->save();

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $user = $student->user;

        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        if ($user !== null) {
            $user->addMediaFromRequest('photo')->toMediaCollection('avatar');
        }

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }
}
