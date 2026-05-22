<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\GetStudentMaterial;
use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function show(string $course, string $material, GetStudentMaterial $action): Response
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $payload = $action->handle($student, $course, $material);

        $materialModel = Material::query()->find($material);
        if ($materialModel) {
            // Causer otomatis di-resolve oleh CauserResolver di AppServiceProvider
            // (returns student aktif untuk guard 'student').
            activity('material_view')
                ->performedOn($materialModel)
                ->log('viewed');
        }

        return Inertia::render('Material/MaterialDetail', $payload);
    }
}
