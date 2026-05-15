<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Filament\Resources\ExamResource;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ExamsRelationManager extends RelationManager
{
    protected static string $relationship = 'exams';

    protected static ?string $title = 'Ujian';

    protected static ?string $modelLabel = 'Ujian';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('order')
                    ->label('#')
                    ->alignCenter()
                    ->width(50),

                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->icon(fn ($state) => $state?->icon())
                    ->color(fn ($state) => match ($state) {
                        ExamModeEnum::OnlineQuiz => 'info',
                        ExamModeEnum::Submission => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y, H:i'),

                TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->formatStateUsing(fn ($state) => "{$state} menit")
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),

                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                SelectFilter::make('mode')
                    ->label('Mode')
                    ->options(collect(ExamModeEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ExamStatusEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),

                TernaryFilter::make('is_published')
                    ->label('Status Publish'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Ujian'),
            ])
            ->actions([
                Action::make('manage')
                    ->label('Kelola')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => ExamResource::getUrl('view', ['record' => $record])),

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
