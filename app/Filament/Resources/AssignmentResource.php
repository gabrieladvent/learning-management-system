<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssignmentResource\Pages;
use App\Filament\Resources\AssignmentResource\RelationManagers;
use App\Models\Assignment;
use App\Models\ClassroomSubject;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssignmentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Pembelajaran';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Tugas';

    protected static ?string $pluralModelLabel = 'Tugas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Tugas')->schema([
                Select::make('classroom_subject_id')
                    ->label('Kelas & Mata Pelajaran')
                    ->options(fn () => ClassroomSubject::with(['classroom', 'subject'])
                        ->get()
                        ->mapWithKeys(fn ($cs) => [
                            $cs->id => "{$cs->classroom->name} — {$cs->subject->name} (Sem {$cs->semester})",
                        ])
                    )
                    ->required()
                    ->searchable()
                    ->columnSpanFull(),

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

            Section::make('Lampiran')->schema([
                SpatieMediaLibraryFileUpload::make('assignment_attachments')
                    ->label('File Lampiran')
                    ->collection('assignment_attachments')
                    ->multiple()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classroomSubject.classroom.name')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('classroomSubject.subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable(),

                TextColumn::make('title')
                    ->label('Judul Tugas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deadline')
                    ->label('Deadline')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->color(fn ($record) => $record->deadline->isPast() ? 'danger' : 'success'),

                TextColumn::make('max_score')
                    ->label('Nilai Maks')
                    ->alignCenter(),

                TextColumn::make('submissions_count')
                    ->label('Pengumpulan')
                    ->counts('submissions')
                    ->alignCenter(),
            ])
            ->defaultSort('deadline')
            ->filters([
                SelectFilter::make('classroom_subject_id')
                    ->label('Kelas & Mapel')
                    ->options(fn () => ClassroomSubject::with(['classroom', 'subject'])
                        ->get()
                        ->mapWithKeys(fn ($cs) => [
                            $cs->id => "{$cs->classroom->name} — {$cs->subject->name}",
                        ])
                    ),
            ])
            ->actions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'create' => Pages\CreateAssignment::route('/create'),
            'view' => Pages\ViewAssignment::route('/{record}'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
        ];
    }
}
