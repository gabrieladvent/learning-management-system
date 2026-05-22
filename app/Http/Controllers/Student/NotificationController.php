<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NotificationController extends Controller
{
    /**
     * List notifikasi untuk siswa login (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $student = Auth::guard('student')->user();

        $paginator = $student->notifications()->paginate(15);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Mark satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $student = Auth::guard('student')->user();

        $notification = $student->notifications()->whereKey($id)->first();

        if (! $notification) {
            throw new NotFoundHttpException('Notifikasi tidak ditemukan.');
        }

        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark semua notifikasi sebagai sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $student = Auth::guard('student')->user();

        $student->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}
