<?php

namespace App\Filament\Resources\MaterialResource\RelationManagers;

use App\Filament\Resources\ExamResource;
use App\Models\Enums\ExamStatusEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
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
                    ->label('Deskripsi')
                    ->columnSpanFull(),

                DateTimePicker::make('starts_at')
                    ->label('Waktu Mulai')
                    ->required()
                    ->native(false),

                TextInput::make('duration_minutes')
                    ->label('Durasi (menit)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(60),

                Select::make('status')
                    ->label('Status Lifecycle')
                    ->options(collect(ExamStatusEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                    ->default(ExamStatusEnum::Draft->value)
                    ->required(),

                Toggle::make('shuffle_questions')
                    ->label('Acak Urutan Soal'),
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
                    ->url(fn ($record) => ExamResource::getUrl('edit', ['record' => $record])),

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
