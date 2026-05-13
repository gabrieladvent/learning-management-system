<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';

    protected static ?string $title = 'Materi';

    protected static ?string $modelLabel = 'Materi';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Materi')->schema([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->maxLength(255),

                TextInput::make('topic')
                    ->label('Topik / Pertemuan')
                    ->maxLength(100)
                    ->placeholder('contoh: Bab 1 — Pengenalan'),
            ])->columns(2),

            Section::make('Konten Materi')
                ->description('Isi salah satu atau kombinasi: teks, file, dan link. Semuanya opsional.')
                ->schema([
                    RichEditor::make('content')
                        ->label('Teks Materi')
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('material_files')
                        ->label('Upload File')
                        ->collection('material_files')
                        ->multiple()
                        ->columnSpanFull(),

                    TextInput::make('link_url')
                        ->label('Link Eksternal')
                        ->url()
                        ->placeholder('https://...')
                        ->prefixIcon('heroicon-o-link')
                        ->columnSpanFull(),
                ]),

            Section::make('Visibility & Jadwal')->schema([
                Toggle::make('is_published')
                    ->label('Publish ke Siswa')
                    ->helperText('Jika OFF, materi tidak akan terlihat siswa meskipun sudah waktunya'),

                DateTimePicker::make('available_from')
                    ->label('Mulai Tampil')
                    ->native(false)
                    ->helperText('Kosongkan untuk langsung saat di-publish'),

                DateTimePicker::make('available_until')
                    ->label('Berhenti Tampil')
                    ->native(false)
                    ->helperText('Kosongkan untuk unlimited'),
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

                TextColumn::make('topic')
                    ->label('Topik')
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean(),

                TextColumn::make('available_from')
                    ->label('Mulai')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('available_until')
                    ->label('Sampai')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Status Publish'),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah Materi'),
            ])
            ->actions([
                Action::make('manage')
                    ->label('Kelola Aktivitas')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn ($record) => route('filament.teacher.resources.materials.view', $record)),

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
