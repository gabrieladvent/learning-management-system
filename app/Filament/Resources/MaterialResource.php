<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ScopesToCurrentTeacher;
use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
use App\Models\ClassroomSubject;
use App\Models\Material;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MaterialResource extends Resource
{
    use ScopesToCurrentTeacher;

    protected static ?string $model = Material::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Materi';

    protected static ?string $pluralModelLabel = 'Materi';

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToCurrentTeacher(
            parent::getEloquentQuery()->with(['classroomSubject.classroom', 'classroomSubject.subject']),
            'classroomSubject',
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informasi Materi')->schema([
                Select::make('classroom_subject_id')
                    ->label('Kelas & Mata Pelajaran')
                    ->options(
                        fn () => ClassroomSubject::with(['classroom', 'subject'])
                            ->get()
                            ->mapWithKeys(fn ($cs) => [
                                $cs->id => "{$cs->classroom->name} — {$cs->subject->name} (Sem {$cs->semester})",
                            ])
                    )
                    ->required()
                    ->searchable()
                    ->columnSpanFull(),

                TextInput::make('title')
                    ->label('Judul Materi')
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
                        ->maxSize(20480)
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Informasi Materi')
                ->icon('heroicon-o-book-open')
                ->schema([
                    TextEntry::make('title')
                        ->label('Judul')
                        ->size(TextEntry\TextEntrySize::Large)
                        ->weight('bold'),

                    TextEntry::make('topic')
                        ->label('Topik / Pertemuan')
                        ->size(TextEntry\TextEntrySize::Large)
                        ->weight('bold')
                        ->placeholder('—'),

                    TextEntry::make('classroomSubject.classroom.name')
                        ->label('Kelas')
                        ->icon('heroicon-o-academic-cap'),

                    TextEntry::make('classroomSubject.subject.name')
                        ->label('Mata Pelajaran')
                        ->icon('heroicon-o-book-open'),
                ])->columns(2),

            InfolistSection::make('Teks Materi')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextEntry::make('content')
                        ->label('')
                        ->html()
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => filled($record->content)),

            InfolistSection::make('File Materi')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    TextEntry::make('material_files_list')
                        ->label('')
                        ->html()
                        ->state(function ($record) {
                            $media = $record->getMedia('material_files');
                            $items = $media->map(function ($m) {
                                $size = number_format($m->size / 1024, 1).' KB';
                                // Disk privat → butuh signed URL (getUrl() unsigned = 403).
                                $url = $m->getTemporaryUrl(now()->addMinutes(30));

                                return '<a href="'.$url.'" target="_blank" '
                                    .'class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 '
                                    .'hover:bg-gray-50 dark:hover:bg-gray-800 transition mb-2">'
                                    .'<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500 flex-shrink-0" '
                                    .'fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" '
                                    .'stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 '
                                    .'0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                                    .'<div class="flex-1 min-w-0">'
                                    .'<div class="font-medium text-sm truncate">'.e($m->name).'</div>'
                                    .'<div class="text-xs text-gray-500">'.e($m->file_name).' · '.$size.'</div>'
                                    .'</div>'
                                    .'<span class="text-xs text-primary-600 dark:text-primary-400">Unduh</span>'
                                    .'</a>';
                            })->implode('');

                            return new HtmlString($items);
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => $record->getMedia('material_files')->isNotEmpty()),

            InfolistSection::make('Link Eksternal')
                ->icon('heroicon-o-link')
                ->schema([
                    TextEntry::make('link_url')
                        ->label('')
                        ->url(fn ($state) => $state)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => filled($record->link_url)),

            InfolistSection::make('Status & Jadwal Tayang')
                ->icon('heroicon-o-clock')
                ->schema([
                    IconEntry::make('is_published')
                        ->label('Status Publish')
                        ->boolean()
                        ->trueIcon('heroicon-o-check-circle')
                        ->falseIcon('heroicon-o-x-circle')
                        ->trueColor('success')
                        ->falseColor('danger'),

                    TextEntry::make('available_from')
                        ->label('Mulai Tampil')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Langsung saat publish')
                        ->icon('heroicon-o-calendar'),

                    TextEntry::make('available_until')
                        ->label('Berhenti Tampil')
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Tanpa batas')
                        ->icon('heroicon-o-calendar'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classroomSubject.classroom.name')
                    ->label('Kelas')
                    ->searchable(),
                TextColumn::make('classroomSubject.subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                TextColumn::make('topic')
                    ->label('Topik')
                    ->toggleable(),
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
            RelationGroup::make('Aktivitas', [
                RelationManagers\AssignmentsRelationManager::class,
                RelationManagers\ExamsRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
            'view' => Pages\ViewMaterial::route('/{record}'),
        ];
    }
}
