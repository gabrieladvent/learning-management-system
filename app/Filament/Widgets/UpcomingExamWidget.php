<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ScopesToCurrentTeacher;
use App\Filament\Resources\ExamResource;
use App\Models\Enums\ExamModeEnum;
use App\Models\Exam;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingExamWidget extends BaseWidget
{
    use ScopesToCurrentTeacher;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Ujian Minggu Ini';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('title')
                    ->label('Judul Ujian')
                    ->searchable()
                    ->url(fn ($record) => ExamResource::getUrl('view', ['record' => $record])),

                TextColumn::make('material.classroomSubject.classroom.name')
                    ->label('Kelas'),

                TextColumn::make('material.classroomSubject.subject.name')
                    ->label('Mata Pelajaran'),

                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => match ($state) {
                        ExamModeEnum::OnlineQuiz => 'info',
                        ExamModeEnum::Submission => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->suffix(' menit'),
            ])
            ->defaultSort('starts_at');
    }

    protected function getQuery(): Builder
    {
        $query = Exam::query()
            ->with(['material.classroomSubject.classroom', 'material.classroomSubject.subject'])
            ->whereBetween('starts_at', [now(), now()->addDays(7)]);

        return static::scopeToCurrentTeacher($query, 'material.classroomSubject');
    }
}
