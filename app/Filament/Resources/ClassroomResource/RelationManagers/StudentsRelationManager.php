<?php

namespace App\Filament\Resources\ClassroomResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\DetachBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Daftar Siswa';

    protected static ?string $modelLabel = 'Siswa';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gender')
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(fn ($state) => $state?->label()),

                TextColumn::make('pivot.enrolled_at')
                    ->label('Terdaftar Sejak')
                    ->dateTime('d M Y'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Tambah Siswa')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->whereDoesntHave('classrooms', fn ($q) => $q->where('classrooms.id', $this->getOwnerRecord()->id)))
                    ->recordSelectSearchColumns(['full_name', 'nisn'])
                    ->form(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        DatePicker::make('enrolled_at')
                            ->label('Tanggal Masuk')
                            ->default(now())
                            ->required(),
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    DetachAction::make()->label('Keluarkan dari Kelas'),
                ]),
            ])
            ->bulkActions([
                DetachBulkAction::make()->label('Keluarkan dari Kelas'),
            ]);
    }
}
