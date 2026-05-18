<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Models\Enums\ExamModeEnum;
use App\Models\Enums\QuestionTypeEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'Hasil Pengerjaan';

    protected static ?string $modelLabel = 'Sesi';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->mode === ExamModeEnum::OnlineQuiz;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student.full_name')
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.nisn')
                    ->label('NISN')
                    ->toggleable(),

                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum mulai'),

                TextColumn::make('submitted_at')
                    ->label('Selesai')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum selesai'),

                IconColumn::make('submitted_at')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn ($record) => filled($record->submitted_at)),

                TextColumn::make('total_score')
                    ->label('Total Skor')
                    ->alignCenter()
                    ->placeholder('—')
                    ->badge()
                    ->color(fn ($state, $record) => $state === null ? 'gray' : ($state >= ($record->exam->max_score * 0.7) ? 'success' : 'warning')),
            ])
            ->filters([
                TernaryFilter::make('submitted_at')
                    ->label('Status')
                    ->nullable()
                    ->trueLabel('Sudah selesai')
                    ->falseLabel('Belum selesai'),
            ])
            ->actions([
                Action::make('viewAnswers')
                    ->label('Lihat Jawaban')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn ($record) => 'Detail Jawaban — '.$record->student->full_name)
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->infolist(function ($record) {
                        return [
                            Placeholder::make('answers')
                                ->label('')
                                ->content(function () use ($record) {
                                    $answers = $record->answers()->with('question')->get();
                                    if ($answers->isEmpty()) {
                                        return new HtmlString('<em class="text-gray-500">Belum ada jawaban</em>');
                                    }
                                    $html = '';
                                    foreach ($answers as $i => $answer) {
                                        $q = $answer->question;
                                        $no = $i + 1;
                                        $type = $q->type?->label() ?? '—';
                                        $isCorrect = $q->correct_answer && strcasecmp($answer->answer ?? '', $q->correct_answer) === 0;
                                        $statusBadge = $answer->score !== null
                                            ? '<span class="px-2 py-0.5 text-xs rounded-full bg-emerald-100 text-emerald-700">Skor: '.$answer->score.'</span>'
                                            : '<span class="px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-700">Belum dinilai</span>';

                                        $html .= '<div class="mb-4 p-4 border rounded-lg dark:border-gray-700">';
                                        $html .= '<div class="flex items-center justify-between mb-2">';
                                        $html .= '<div class="font-semibold">Soal '.$no.' <span class="ml-2 text-xs text-gray-500">('.$type.' · '.$q->score.' poin)</span></div>';
                                        $html .= $statusBadge.'</div>';
                                        $html .= '<div class="prose dark:prose-invert max-w-none mb-3">'.$q->question.'</div>';
                                        $html .= '<div class="bg-gray-50 dark:bg-gray-800 p-3 rounded text-sm">';
                                        $html .= '<div class="text-xs text-gray-500 mb-1">Jawaban Siswa:</div>';
                                        $html .= '<div>'.e($answer->answer ?? '—').'</div>';
                                        $html .= '</div>';
                                        if ($q->correct_answer) {
                                            $html .= '<div class="mt-2 text-xs text-gray-500">Kunci: <strong>'.e($q->correct_answer).'</strong>';
                                            $html .= $isCorrect ? ' <span class="text-emerald-600">✓ Benar</span>' : ' <span class="text-rose-600">✗ Salah</span>';
                                            $html .= '</div>';
                                        }
                                        if ($answer->feedback) {
                                            $html .= '<div class="mt-2 p-2 bg-sky-50 dark:bg-sky-900/20 rounded text-xs">';
                                            $html .= '<strong>Feedback Guru:</strong> '.e($answer->feedback);
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                    }

                                    return new HtmlString($html);
                                }),
                        ];
                    }),

                ActionGroup::make([
                    Action::make('gradeEssays')
                        ->label('Beri Nilai Essay')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->visible(fn ($record) => $record->answers()->whereHas('question', fn ($q) => $q->where('type', QuestionTypeEnum::Essay->value))->exists())
                        ->form(function ($record) {
                            $essayAnswers = $record->answers()
                                ->whereHas('question', fn ($q) => $q->where('type', QuestionTypeEnum::Essay->value))
                                ->with('question')
                                ->get();

                            return [
                                Repeater::make('essay_grades')
                                    ->label('')
                                    ->schema([
                                        Placeholder::make('question_text')
                                            ->label('Pertanyaan')
                                            ->content(fn ($get) => new HtmlString($get('question_html') ?? '')),
                                        Placeholder::make('answer_text')
                                            ->label('Jawaban Siswa')
                                            ->content(fn ($get) => new HtmlString('<div class="p-3 bg-gray-50 dark:bg-gray-800 rounded">'.e($get('answer') ?? '—').'</div>')),
                                        TextInput::make('score')
                                            ->label('Skor')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(fn ($get) => $get('max_score')),
                                        Textarea::make('feedback')
                                            ->label('Feedback')
                                            ->rows(2),
                                    ])
                                    ->default(
                                        $essayAnswers->map(fn ($a) => [
                                            'answer_id' => $a->id,
                                            'question_html' => $a->question->question,
                                            'answer' => $a->answer,
                                            'score' => $a->score,
                                            'feedback' => $a->feedback,
                                            'max_score' => $a->question->score,
                                        ])->toArray()
                                    )
                                    ->disableItemCreation()
                                    ->disableItemDeletion()
                                    ->disableItemMovement()
                                    ->columnSpanFull(),
                            ];
                        })
                        ->action(function (array $data, $record) {
                            foreach ($data['essay_grades'] ?? [] as $grade) {
                                if (! isset($grade['answer_id'])) {
                                    continue;
                                }
                                $record->answers()->where('id', $grade['answer_id'])->update([
                                    'score' => $grade['score'] ?? null,
                                    'feedback' => $grade['feedback'] ?? null,
                                ]);
                            }
                            // Recalculate total score
                            $total = $record->answers()->sum('score');
                            $record->update(['total_score' => $total]);

                            Notification::make()
                                ->title('Nilai essay tersimpan')
                                ->body("Total skor diperbarui: {$total}")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
