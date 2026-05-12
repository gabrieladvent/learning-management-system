<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClassroomResource\Pages;
use App\Filament\Resources\ClassroomResource\RelationManagers;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Teacher;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Manajemen Kelas';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Kelas';

    protected static ?string $pluralModelLabel = 'Kelas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Kelas')->schema([
                TextInput::make('name')
                    ->label('Nama Kelas')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('contoh: X IPA 1'),

                TextInput::make('grade_level')
                    ->label('Tingkat')
                    ->required()
                    ->maxLength(10)
                    ->placeholder('contoh: X, XI, XII'),

                TextInput::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->required()
                    ->maxLength(20)
                    ->placeholder('contoh: 2025/2026'),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ])->columns(2),

            Section::make('Penugasan')->schema([
                Select::make('school_id')
                    ->label('Sekolah')
                    ->options(School::query()->where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('teacher_id')
                    ->label('Wali Kelas')
                    ->options(Teacher::query()->pluck('full_name', 'id'))
                    ->required()
                    ->searchable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Kelas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('academic_year')
                    ->label('Tahun Ajaran')
                    ->sortable(),

                TextColumn::make('school.name')
                    ->label('Sekolah')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('students_count')
                    ->label('Siswa')
                    ->counts('students')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('school_id')
                    ->label('Sekolah')
                    ->options(School::query()->pluck('name', 'id')),

                SelectFilter::make('grade_level')
                    ->label('Tingkat')
                    ->options(['X' => 'X', 'XI' => 'XI', 'XII' => 'XII']),

                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
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
            RelationManagers\StudentsRelationManager::class,
            RelationManagers\ClassroomSubjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClassrooms::route('/'),
            'create' => Pages\CreateClassroom::route('/create'),
            'view' => Pages\ViewClassroom::route('/{record}'),
            'edit' => Pages\EditClassroom::route('/{record}/edit'),
        ];
    }
}
