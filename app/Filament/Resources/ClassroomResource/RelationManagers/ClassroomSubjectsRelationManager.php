<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use App\Models\Subject;
use App\Models\Teacher;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassroomSubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'classroomSubjects';

    protected static ?string $title = 'Mata Pelajaran';

    protected static ?string $modelLabel = 'Mata Pelajaran';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('subject_id')
                ->label('Mata Pelajaran')
                ->options(Subject::query()->orderBy('name')->pluck('name', 'id'))
                ->required()
                ->searchable(),

            Select::make('teacher_id')
                ->label('Guru Pengajar')
                ->options(Teacher::query()->orderBy('full_name')->pluck('full_name', 'id'))
                ->required()
                ->searchable(),

            Select::make('academic_year')
                ->label('Tahun Ajaran')
                ->options(function () {
                    $options = [];
                    foreach (range((int) date('Y') - 1, (int) date('Y') + 1) as $year) {
                        $label = $year.'/'.($year + 1);
                        $options[$label] = $label;
                    }

                    return $options;
                })
                ->default(date('Y').'/'.(date('Y') + 1))
                ->required(),

            Select::make('semester')
                ->label('Semester')
                ->options([1 => 'Semester 1', 2 => 'Semester 2'])
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject.name')
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject.code')
                    ->label('Kode')
                    ->badge(),

                TextColumn::make('teacher.full_name')
                    ->label('Guru Pengajar')
                    ->searchable(),

                TextColumn::make('academic_year')
                    ->label('Tahun Ajaran'),

                TextColumn::make('semester')
                    ->label('Semester')
                    ->formatStateUsing(fn ($state) => "Semester {$state}"),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Mata Pelajaran'),
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
