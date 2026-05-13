<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssignmentResource\Pages;
use App\Filament\Resources\AssignmentResource\RelationManagers;
use App\Models\Assignment;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Tugas';

    protected static ?string $pluralModelLabel = 'Tugas';

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
            Section::make('Informasi Tugas')->schema([
                TextInput::make('title')
                    ->label('Judul Tugas')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                RichEditor::make('description')
                    ->label('Deskripsi / Soal')
                    ->columnSpanFull(),

                DateTimePicker::make('deadline')
                    ->label('Batas Waktu')
                    ->required()
                    ->native(false),

                TextInput::make('max_score')
                    ->label('Nilai Maksimal')
                    ->numeric()
                    ->default(100)
                    ->minValue(1),
            ])->columns(2),

            Section::make('Lampiran Soal')->schema([
                SpatieMediaLibraryFileUpload::make('assignment_attachments')
                    ->label('File Lampiran (dari Guru)')
                    ->collection('assignment_attachments')
                    ->multiple()
                    ->columnSpanFull(),
            ]),

            Section::make('Aturan Pengumpulan Siswa')
                ->description('Siswa selalu bisa mengumpulkan tugas via teks (essay) maupun file. Atur batasan file di sini.')
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
                        ->default(Assignment::DEFAULT_FILE_TYPES)
                        ->bulkToggleable()
                        ->columnSpanFull(),

                    TextInput::make('max_file_size_mb')
                        ->label('Maksimal Ukuran File per File (MB)')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(100)
                        ->suffix('MB')
                        ->required(),
                ])->columns(2),

            Section::make('Visibility & Jadwal')->schema([
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
                    ->label('Judul Tugas')
                    ->searchable(),
                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->dateTime('d M Y, H:i'),
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
            RelationManagers\SubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
            'view' => Pages\ViewAssignment::route('/{record}'),
        ];
    }
}
