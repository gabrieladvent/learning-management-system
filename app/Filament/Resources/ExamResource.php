<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Filament\Resources\ExamResource\RelationManagers;
use App\Models\Enums\ExamModeEnum;
use App\Models\Enums\ExamStatusEnum;
use App\Models\Exam;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Ujian';

    protected static ?string $pluralModelLabel = 'Ujian';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['material.classroomSubject']);

        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        if ($user?->teacher) {
            return $query->whereHas('material.classroomSubject', fn ($q) => $q->where('teacher_id', $user->teacher->id));
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Ujian')->schema([
                TextInput::make('title')
                    ->label('Judul Ujian')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->label('Deskripsi / Petunjuk')
                    ->columnSpanFull(),
            ]),

            Section::make('Mode Ujian')->schema([
                Radio::make('mode')
                    ->label('Pilih cara siswa mengerjakan ujian')
                    ->options(collect(ExamModeEnum::cases())->mapWithKeys(fn ($e) => [
                        $e->value => $e->label().' — '.$e->description(),
                    ]))
                    ->default(ExamModeEnum::OnlineQuiz->value)
                    ->required()
                    ->live()
                    ->columnSpanFull(),
            ]),

            Section::make('Jadwal & Durasi')->schema([
                DateTimePicker::make('starts_at')
                    ->label('Waktu Mulai')
                    ->required()
                    ->native(false),

                TextInput::make('duration_minutes')
                    ->label('Durasi (menit)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(60)
                    ->suffix('menit'),

                TextInput::make('max_score')
                    ->label('Nilai Maksimal')
                    ->numeric()
                    ->required()
                    ->default(100)
                    ->minValue(1),

                Select::make('status')
                    ->label('Status Lifecycle')
                    ->options(collect(ExamStatusEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                    ->default(ExamStatusEnum::Draft->value)
                    ->required(),
            ])->columns(2),

            Section::make('Pengaturan Soal Interaktif')
                ->visible(fn (Get $get) => $get('mode') === ExamModeEnum::OnlineQuiz->value)
                ->schema([
                    Toggle::make('shuffle_questions')
                        ->label('Acak Urutan Soal per Siswa')
                        ->helperText('Tiap siswa akan mendapat urutan soal yang berbeda'),
                ]),

            Section::make('Aturan Pengumpulan')
                ->visible(fn (Get $get) => $get('mode') === ExamModeEnum::Submission->value)
                ->description('Siswa selalu bisa kumpulkan via teks, file, atau link. Atur batasan file di sini.')
                ->schema([
                    CheckboxList::make('allowed_file_types')
                        ->label('Tipe File yang Diizinkan')
                        ->options([
                            'pdf' => 'PDF',
                            'doc' => 'Word (.doc)',
                            'docx' => 'Word (.docx)',
                            'xls' => 'Excel (.xls)',
                            'xlsx' => 'Excel (.xlsx)',
                            'ppt' => 'PowerPoint (.ppt)',
                            'pptx' => 'PowerPoint (.pptx)',
                            'jpg' => 'JPG',
                            'jpeg' => 'JPEG',
                            'png' => 'PNG',
                            'gif' => 'GIF',
                            'zip' => 'ZIP',
                            'rar' => 'RAR',
                            'txt' => 'Text (.txt)',
                            'mp3' => 'MP3 (audio)',
                            'mp4' => 'MP4 (video)',
                        ])
                        ->columns(3)
                        ->default(Exam::DEFAULT_FILE_TYPES)
                        ->bulkToggleable()
                        ->columnSpanFull(),

                    TextInput::make('max_file_size_mb')
                        ->label('Maksimal Ukuran File per File (MB)')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('MB'),
                ])->columns(2),

            Section::make('Visibility & Jadwal Tayang')->schema([
                Toggle::make('is_published')
                    ->label('Publish ke Siswa'),

                DateTimePicker::make('available_from')
                    ->label('Mulai Tampil')
                    ->native(false),

                DateTimePicker::make('available_until')
                    ->label('Berhenti Tampil')
                    ->native(false),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('material.classroomSubject.subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable(),
                TextColumn::make('material.title')
                    ->label('Materi')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Judul Ujian')
                    ->searchable(),
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
                    ->dateTime('d M Y, H:i'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),
                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean(),
            ])
            ->actions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\QuestionsRelationManager::class,
            RelationManagers\SubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
            'view' => Pages\ViewExam::route('/{record}'),
        ];
    }
}
