<?php

namespace App\Filament\Resources\ExamResource\RelationManagers;

use App\Models\Enums\ExamModeEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Pengumpulan Siswa';

    protected static ?string $modelLabel = 'Pengumpulan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->mode === ExamModeEnum::Submission;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('score')
                ->label('Nilai')
                ->numeric()
                ->minValue(0)
                ->maxValue(fn () => $this->getOwnerRecord()->max_score),

            Textarea::make('feedback')
                ->label('Feedback / Catatan')
                ->rows(3)
                ->columnSpanFull(),
        ]);
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

                TextColumn::make('submitted_at')
                    ->label('Waktu Kumpul')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum mengumpulkan')
                    ->sortable(),

                IconColumn::make('submitted_at')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn ($record) => filled($record->submitted_at)),

                TextColumn::make('score')
                    ->label('Nilai')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('feedback')
                    ->label('Feedback')
                    ->limit(40)
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('submitted_at')
                    ->label('Status Pengumpulan')
                    ->nullable()
                    ->trueLabel('Sudah mengumpulkan')
                    ->falseLabel('Belum mengumpulkan'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('Beri Nilai'),
                ]),
            ]);
    }
}
