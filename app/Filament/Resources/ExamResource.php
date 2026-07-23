<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToCurrentTeacher;
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
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
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
    use ScopesToCurrentTeacher;

    protected static ?string $model = Exam::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Ujian';

    protected static ?string $pluralModelLabel = 'Ujian';

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToCurrentTeacher(
            parent::getEloquentQuery()->with(['material.classroomSubject']),
            'material.classroomSubject',
        );
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
                    ->label('Publish ke Siswa')
                    ->helperText('Aktifkan untuk membuat ujian terlihat siswa. Saat publish, siswa di kelas terkait otomatis dapat notifikasi.'),

                DateTimePicker::make('available_from')
                    ->label('Mulai Tampil')
                    ->native(false)
                    ->helperText('Ujian akan terlihat di dashboard siswa mulai tanggal/jam ini. Kosongkan = langsung tampil saat di-publish.'),

                DateTimePicker::make('available_until')
                    ->label('Berhenti Tampil')
                    ->native(false)
                    ->helperText('Window pengerjaan ditutup pada tanggal ini (hard cutoff). Kosongkan = ujian tetap bisa dikerjakan tanpa batas.'),

                DateTimePicker::make('results_released_at')
                    ->label('Rilis Hasil')
                    ->native(false)
                    ->helperText('Tanggal/jam saat hasil ujian dirilis ke siswa. Sebelum tanggal ini, siswa tidak melihat skor (tetap bisa lihat status submitted). Kosongkan = hasil langsung tampil setelah dinilai.'),
            ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Informasi Ujian')
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    TextEntry::make('title')
                        ->label('Judul Ujian')
                        ->size(TextEntry\TextEntrySize::Large)
                        ->weight('bold')
                        ->columnSpanFull(),

                    TextEntry::make('material.classroomSubject.classroom.name')
                        ->label('Kelas')
                        ->icon('heroicon-o-academic-cap'),

                    TextEntry::make('material.classroomSubject.subject.name')
                        ->label('Mata Pelajaran')
                        ->icon('heroicon-o-book-open'),

                    TextEntry::make('material.title')
                        ->label('Materi Terkait')
                        ->icon('heroicon-o-document-text')
                        ->columnSpanFull(),

                    TextEntry::make('description')
                        ->label('Deskripsi / Petunjuk')
                        ->html()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])->columns(2),

            InfolistSection::make('Mode & Pengaturan Ujian')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    TextEntry::make('mode')
                        ->label('Mode Ujian')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state?->label())
                        ->icon(fn ($state) => $state?->icon())
                        ->color(fn ($state) => match ($state) {
                            ExamModeEnum::OnlineQuiz => 'info',
                            ExamModeEnum::Submission => 'warning',
                            default => 'gray',
                        }),

                    TextEntry::make('status')
                        ->label('Status Lifecycle')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state?->label())
                        ->color(fn ($state) => $state?->color()),

                    IconEntry::make('shuffle_questions')
                        ->label('Acak Soal')
                        ->boolean()
                        ->visible(fn ($record) => $record->mode === ExamModeEnum::OnlineQuiz),
                ])->columns(3),

            InfolistSection::make('Jadwal & Penilaian')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    TextEntry::make('starts_at')
                        ->label('Waktu Mulai')
                        ->dateTime('d M Y, H:i')
                        ->icon('heroicon-o-clock'),

                    TextEntry::make('duration_minutes')
                        ->label('Durasi')
                        ->suffix(' menit')
                        ->icon('heroicon-o-clock'),

                    TextEntry::make('max_score')
                        ->label('Nilai Maksimal')
                        ->numeric()
                        ->suffix(' poin')
                        ->icon('heroicon-o-star'),
                ])->columns(3),

            InfolistSection::make('Aturan Pengumpulan')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn ($record) => $record->mode === ExamModeEnum::Submission)
                ->schema([
                    TextEntry::make('allowed_file_types')
                        ->label('Tipe File yang Diizinkan')
                        ->badge()
                        ->separator(',')
                        ->color('info')
                        ->placeholder('—'),

                    TextEntry::make('max_file_size_mb')
                        ->label('Maksimal Ukuran File')
                        ->suffix(' MB / file')
                        ->icon('heroicon-o-document'),
                ])->columns(2),

            InfolistSection::make('Ringkasan Soal & Sesi')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    TextEntry::make('questions_count')
                        ->label('Jumlah Soal')
                        ->state(fn ($record) => $record->questions()->count().' soal')
                        ->icon('heroicon-o-list-bullet')
                        ->visible(fn ($record) => $record->mode === ExamModeEnum::OnlineQuiz),

                    TextEntry::make('sessions_count')
                        ->label('Sesi Pengerjaan')
                        ->state(fn ($record) => $record->sessions()->count().' siswa')
                        ->icon('heroicon-o-users')
                        ->visible(fn ($record) => $record->mode === ExamModeEnum::OnlineQuiz),

                    TextEntry::make('submissions_count')
                        ->label('Pengumpulan')
                        ->state(fn ($record) => $record->submissions()->count().' siswa')
                        ->icon('heroicon-o-inbox-arrow-down')
                        ->visible(fn ($record) => $record->mode === ExamModeEnum::Submission),
                ])->columns(3),

            InfolistSection::make('Status & Jadwal Tayang')
                ->icon('heroicon-o-eye')
                ->schema([
                    IconEntry::make('is_published')
                        ->label('Status Publish')
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger'),

                    TextEntry::make('available_from')
                        ->label('Mulai Tampil')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Langsung saat publish')
                        ->icon('heroicon-o-calendar'),

                    TextEntry::make('available_until')
                        ->label('Berhenti Tampil')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Tanpa batas')
                        ->icon('heroicon-o-calendar'),
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
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('sessions_count')
                    ->label('Sesi')
                    ->counts('sessions')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->toggleable(),
                TextColumn::make('submissions_count')
                    ->label('Pengumpulan')
                    ->counts('submissions')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->toggleable(),
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
            RelationManagers\SessionsRelationManager::class,
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
