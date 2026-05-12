<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Models\ClassroomSubject;
use App\Models\Enums\MaterialTypeEnum;
use App\Models\Material;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
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

class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Pembelajaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Materi';

    protected static ?string $pluralModelLabel = 'Materi';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Materi')->schema([
                Select::make('classroom_subject_id')
                    ->label('Kelas & Mata Pelajaran')
                    ->options(fn () => ClassroomSubject::with(['classroom', 'subject'])
                        ->get()
                        ->mapWithKeys(fn ($cs) => [
                            $cs->id => "{$cs->classroom->name} — {$cs->subject->name} (Sem {$cs->semester})",
                        ])
                    )
                    ->required()
                    ->searchable(),

                TextInput::make('title')
                    ->label('Judul Materi')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('topic')
                    ->label('Topik / Pertemuan')
                    ->maxLength(100)
                    ->placeholder('contoh: Bab 1 — Persamaan Linear'),

                TextInput::make('order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0),

                Select::make('type')
                    ->label('Tipe Materi')
                    ->options(collect(MaterialTypeEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                    ->required()
                    ->live(),

                DateTimePicker::make('published_at')
                    ->label('Jadwal Publish')
                    ->native(false)
                    ->helperText('Kosongkan untuk langsung publish'),
            ])->columns(2),

            Section::make('Konten')->schema([
                RichEditor::make('content')
                    ->label('Teks Materi')
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === MaterialTypeEnum::Text->value),

                TextInput::make('content')
                    ->label('URL Link')
                    ->url()
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === MaterialTypeEnum::Link->value),

                SpatieMediaLibraryFileUpload::make('material_files')
                    ->label('Upload File')
                    ->collection('material_files')
                    ->multiple()
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('type') === MaterialTypeEnum::File->value),
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
                    ->label('Judul')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('topic')
                    ->label('Topik')
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => match ($state) {
                        MaterialTypeEnum::Text => 'info',
                        MaterialTypeEnum::File => 'warning',
                        MaterialTypeEnum::Link => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('order')
                    ->label('Urutan')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('published_at')
                    ->label('Dipublish')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->placeholder('Belum dijadwal'),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                SelectFilter::make('classroom_subject_id')
                    ->label('Kelas & Mapel')
                    ->options(fn () => ClassroomSubject::with(['classroom', 'subject'])
                        ->get()
                        ->mapWithKeys(fn ($cs) => [
                            $cs->id => "{$cs->classroom->name} — {$cs->subject->name}",
                        ])
                    ),

                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options(collect(MaterialTypeEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'view' => Pages\ViewMaterial::route('/{record}'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}
