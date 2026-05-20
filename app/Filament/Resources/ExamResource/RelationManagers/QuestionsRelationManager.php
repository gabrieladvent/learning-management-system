<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Models\Enums\ExamModeEnum;
use App\Models\Enums\QuestionTypeEnum;
use App\Services\ExamGrader;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Daftar Soal';

    protected static ?string $modelLabel = 'Soal';

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
        $richEditorButtons = ['bold', 'italic', 'underline', 'strike', 'orderedList', 'bulletList', 'link', 'undo', 'redo'];

        return $form->schema([
            Section::make('Soal')->schema([
                Select::make('type')
                    ->label('Tipe Soal')
                    ->options(collect(QuestionTypeEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                    ->required()
                    ->live()
                    ->default(QuestionTypeEnum::MultipleChoice->value),

                TextInput::make('score')
                    ->label('Bobot Nilai')
                    ->numeric()
                    ->required()
                    ->default(10)
                    ->minValue(0)
                    ->suffix('poin'),

                Placeholder::make('math_hint')
                    ->label('')
                    ->content(new HtmlString(
                        '<div class="text-xs text-gray-600 dark:text-gray-400 flex items-center gap-2">'
                        .'<svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                        .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" '
                        .'d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                        .'</svg>'
                        .'Butuh formula matematika? Gunakan <strong>Editor Formula</strong> di pojok kanan bawah layar.'
                        .'</div>'
                    ))
                    ->columnSpanFull(),

                RichEditor::make('question')
                    ->label('Pertanyaan')
                    ->required()
                    ->toolbarButtons($richEditorButtons)
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Pilihan Jawaban')
                ->visible(fn (Get $get) => $get('type') === QuestionTypeEnum::MultipleChoice->value)
                ->schema([
                    Repeater::make('options')
                        ->label('Opsi Jawaban')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(5)
                                ->placeholder('A')
                                ->columnSpan(1),

                            RichEditor::make('text')
                                ->label('Teks Opsi')
                                ->required()
                                ->toolbarButtons($richEditorButtons)
                                ->columnSpan(3),
                        ])
                        ->columns(4)
                        ->minItems(2)
                        ->maxItems(6)
                        ->default([
                            ['label' => 'A', 'text' => null],
                            ['label' => 'B', 'text' => null],
                            ['label' => 'C', 'text' => null],
                            ['label' => 'D', 'text' => null],
                        ])
                        ->formatStateUsing(function ($state) {
                            if (! is_array($state) || empty($state)) {
                                return [
                                    ['label' => 'A', 'text' => null],
                                    ['label' => 'B', 'text' => null],
                                    ['label' => 'C', 'text' => null],
                                    ['label' => 'D', 'text' => null],
                                ];
                            }

                            $first = reset($state);
                            if (is_array($first) && array_key_exists('label', $first)) {
                                return $state;
                            }

                            return collect($state)->map(fn ($text, $label) => [
                                'label' => (string) $label,
                                'text' => $text,
                            ])->values()->all();
                        })
                        ->dehydrateStateUsing(function ($state) {
                            return collect($state ?? [])
                                ->filter(fn ($row) => filled($row['label'] ?? null))
                                ->mapWithKeys(fn ($row) => [$row['label'] => $row['text'] ?? ''])
                                ->all();
                        })
                        ->reorderableWithButtons()
                        ->columnSpanFull(),

                    TextInput::make('correct_answer')
                        ->label('Jawaban Benar (Label)')
                        ->placeholder('contoh: B')
                        ->required()
                        ->maxLength(5)
                        ->helperText('Masukkan label opsi yang benar (A/B/C/D)'),
                ]),

            Section::make('Jawaban Benar')
                ->visible(fn (Get $get) => $get('type') === QuestionTypeEnum::ShortAnswer->value)
                ->schema([
                    TextInput::make('correct_answer')
                        ->label('Kunci Jawaban')
                        ->required()
                        ->helperText('Jawaban siswa akan dicocokkan persis dengan kunci ini (case-insensitive)'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question')
            ->columns([
                TextColumn::make('order')
                    ->label('No')
                    ->alignCenter()
                    ->width(60),

                TextColumn::make('question')
                    ->label('Pertanyaan')
                    ->formatStateUsing(fn ($state) => trim(html_entity_decode(strip_tags($state ?? ''))))
                    ->limit(60)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => match ($state) {
                        QuestionTypeEnum::MultipleChoice => 'info',
                        QuestionTypeEnum::ShortAnswer => 'warning',
                        QuestionTypeEnum::Essay => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('correct_answer')
                    ->label('Kunci')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn ($record) => $record->type === QuestionTypeEnum::Essay ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state, $record) => $record->type === QuestionTypeEnum::Essay
                        ? 'Manual'
                        : ($state ?? '—'))
                    ->toggleable(),

                TextColumn::make('score')
                    ->label('Bobot')
                    ->alignCenter()
                    ->suffix(' poin'),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->headerActions([
                CreateAction::make()->label('Tambah Soal'),
                Action::make('regrade')
                    ->label('Hitung Ulang Nilai')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Auto-grader akan menghitung ulang skor Multiple Choice + Short Answer untuk semua sesi yang sudah submitted. Skor essay yang sudah dinilai guru tidak akan diubah.')
                    ->action(function () {
                        $exam = $this->getOwnerRecord();
                        $grader = app(ExamGrader::class);
                        $count = 0;

                        $exam->sessions()
                            ->whereNotNull('submitted_at')
                            ->get()
                            ->each(function ($session) use ($grader, &$count) {
                                $grader->grade($session);
                                $count++;
                            });

                        Notification::make()
                            ->title("$count sesi dihitung ulang")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
