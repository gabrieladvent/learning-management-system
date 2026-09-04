<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\SetCoursePin;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function pin(Request $request, string $course, SetCoursePin $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $action->handle($student, $course, pinned: true);

        return ApiResponse::success(message: 'Mata pelajaran disematkan.');
    }

    public function unpin(Request $request, string $course, SetCoursePin $action): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $action->handle($student, $course, pinned: false);

        return ApiResponse::success(message: 'Sematan dilepas.');
    }
}
