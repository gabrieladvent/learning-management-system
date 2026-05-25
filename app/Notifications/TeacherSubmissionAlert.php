<?php

namespace App\Notifications;

use App\Filament\Resources\AssignmentResource;
use App\Filament\Resources\ExamResource;
use App\Models\AssignmentSubmission;
use App\Models\ExamSession;
use App\Models\ExamSubmission;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;

class TeacherSubmissionAlert
{
    /**
     * Notify guru bahwa siswa baru mengumpulkan tugas/ujian.
     * Notification ditulis ke tabel `notifications` (database channel)
     * dan akan muncul di bell-icon Filament guru.
     */
    public static function forAssignment(AssignmentSubmission $submission, bool $isResubmit = false): void
    {
        $teacherUser = self::resolveTeacherUser(
            $submission->assignment?->material?->classroomSubject?->teacher?->user_id
        );

        if (! $teacherUser) {
            return;
        }

        $studentName = $submission->student?->full_name ?? 'Siswa';
        $title = $submission->assignment?->title ?? 'Tugas';

        $heading = $isResubmit
            ? "Tugas direvisi: {$title}"
            : "Tugas baru dikumpulkan: {$title}";

        FilamentNotification::make()
            ->title($heading)
            ->body("Siswa: {$studentName}")
            ->icon($isResubmit ? 'heroicon-o-arrow-path' : 'heroicon-o-clipboard-document-list')
            ->iconColor($isResubmit ? 'warning' : 'primary')
            ->actions([
                Action::make('view')
                    ->label('Lihat')
                    ->url(self::assignmentUrl($submission)),
            ])
            ->sendToDatabase($teacherUser);
    }

    public static function forExamSession(ExamSession $session): void
    {
        $teacherUser = self::resolveTeacherUser(
            $session->exam?->material?->classroomSubject?->teacher?->user_id
        );

        if (! $teacherUser) {
            return;
        }

        $studentName = $session->student?->full_name ?? 'Siswa';
        $title = $session->exam?->title ?? 'Ujian';

        FilamentNotification::make()
            ->title("Ujian online selesai: {$title}")
            ->body("Siswa: {$studentName}")
            ->icon('heroicon-o-clipboard-document-check')
            ->iconColor('info')
            ->actions([
                Action::make('view')
                    ->label('Lihat')
                    ->url(self::examUrl($session->exam_id)),
            ])
            ->sendToDatabase($teacherUser);
    }

    public static function forExamSubmission(ExamSubmission $submission): void
    {
        $teacherUser = self::resolveTeacherUser(
            $submission->exam?->material?->classroomSubject?->teacher?->user_id
        );

        if (! $teacherUser) {
            return;
        }

        $studentName = $submission->student?->full_name ?? 'Siswa';
        $title = $submission->exam?->title ?? 'Ujian';

        FilamentNotification::make()
            ->title("Ujian dikumpulkan: {$title}")
            ->body("Siswa: {$studentName}")
            ->icon('heroicon-o-arrow-up-tray')
            ->iconColor('warning')
            ->actions([
                Action::make('view')
                    ->label('Lihat')
                    ->url(self::examUrl($submission->exam_id)),
            ])
            ->sendToDatabase($teacherUser);
    }

    protected static function resolveTeacherUser(?string $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }

    protected static function assignmentUrl(AssignmentSubmission $submission): ?string
    {
        $id = $submission->assignment_id;
        if (! $id) {
            return null;
        }

        try {
            return AssignmentResource::getUrl('view', ['record' => $id]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function examUrl(?string $examId): ?string
    {
        if (! $examId) {
            return null;
        }

        try {
            return ExamResource::getUrl('view', ['record' => $examId]);
        } catch (\Throwable) {
            return null;
        }
    }
}
