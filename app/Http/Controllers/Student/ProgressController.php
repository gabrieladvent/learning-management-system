<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\RecordLearningProgress;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ProgressController extends Controller
{
    public function heartbeat(Request $request, RecordLearningProgress $action): Response|JsonResponse
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $maxKb = (int) config('learning_progress.validation.max_payload_kb', 32);
        $size = strlen((string) $request->getContent());
        if ($size > $maxKb * 1024) {
            return response()->json(['message' => 'payload too large'], 413);
        }

        $action->handle($student, $request->all());

        return response()->noContent();
    }

    public function dismissDisclosure(Request $request): Response
    {
        $student = Auth::guard('student')->user();
        $user = $student?->user;

        if ($user && $user->tracking_disclosure_seen_at === null) {
            $user->forceFill(['tracking_disclosure_seen_at' => now()])->save();
        }

        return response()->noContent();
    }
}
