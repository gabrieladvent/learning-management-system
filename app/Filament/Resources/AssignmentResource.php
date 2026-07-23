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
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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
                    ->maxSize(20480)
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
                    ->label('Publish ke Siswa')
                    ->helperText('Aktifkan untuk membuat tugas terlihat siswa. Saat publish, siswa di kelas terkait otomatis dapat notifikasi.'),

                DateTimePicker::make('available_from')
                    ->label('Mulai Tampil')
                    ->native(false)
                    ->helperText('Tugas baru akan terlihat siswa mulai tanggal/jam ini. Kosongkan = langsung tampil saat di-publish.'),

                DateTimePicker::make('available_until')
                    ->label('Berhenti Tampil')
                    ->native(false)
                    ->helperText('Window pengumpulan ditutup pada tanggal ini (hard cutoff). Kosongkan = tetap bisa dikumpulkan sampai deadline.'),
            ])->columns(3),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Informasi Tugas')
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    TextEntry::make('title')
                        ->label('Judul Tugas')
                        ->size(TextEntry\TextEntrySize::Large)
                        ->weight('bold')
                        ->columnSpanFull(),

                    TextEntry::make('material.classroomSubject.classroom.name')
                        ->label('Kelas')
                        ->icon('heroicon-o-academic-cap'),

                    TextEntry::make('material.classroomSubject.subject.name')
                        ->label('Mata Pelajaran')
                        ->icon('heroicon-o-book-open'),

                    TextEntry::make('material.title')
                        ->label('Materi Terkait')
                        ->icon('heroicon-o-document-text')
                        ->columnSpanFull(),

                    TextEntry::make('description')
                        ->label('Deskripsi / Soal')
                        ->html()
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])->columns(2),

            InfolistSection::make('Jadwal & Penilaian')
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    TextEntry::make('deadline')
                        ->label('Batas Waktu')
                        ->dateTime('d M Y, H:i')
                        ->badge()
                        ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success')
                        ->icon('heroicon-o-clock'),

                    TextEntry::make('max_score')
                        ->label('Nilai Maksimal')
                        ->numeric()
                        ->suffix(' poin')
                        ->icon('heroicon-o-star'),

                    TextEntry::make('submissions_count')
                        ->label('Pengumpulan')
                        ->state(fn ($record) => $record->submissions()->count().' siswa mengumpulkan')
                        ->icon('heroicon-o-inbox-arrow-down'),
                ])->columns(3),

            InfolistSection::make('Lampiran Soal dari Guru')
                ->icon('heroicon-o-paper-clip')
                ->schema([
                    TextEntry::make('assignment_attachments_list')
                        ->label('')
                        ->html()
                        ->state(function ($record) {
                            $media = $record->getMedia('assignment_attachments');
                            $items = $media->map(function ($m) {
                                $size = number_format($m->size / 1024, 1).' KB';
                                // Disk privat → butuh signed URL (getUrl() unsigned = 403).
                                $url = $m->getTemporaryUrl(now()->addMinutes(30));

                                return '<a href="'.$url.'" target="_blank" '
                                    .'class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 '
                                    .'hover:bg-gray-50 dark:hover:bg-gray-800 transition mb-2">'
                                    .'<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-primary-500 flex-shrink-0" '
                                    .'fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" '
                                    .'stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
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
                ->visible(fn ($record) => $record->getMedia('assignment_attachments')->isNotEmpty()),

            InfolistSection::make('Aturan Pengumpulan Siswa')
                ->icon('heroicon-o-cog-6-tooth')
                ->schema([
                    TextEntry::make('allowed_file_types')
                        ->label('Tipe File yang Diizinkan')
                        ->badge()
                        ->separator(',')
                        ->color('info')
                        ->placeholder('—'),

                    TextEntry::make('max_file_size_mb')
                        ->label('Maksimal Ukuran File')
                        ->suffix(' MB / file')
                        ->icon('heroicon-o-document'),
                ])->columns(2),

            InfolistSection::make('Status & Jadwal Tayang')
                ->icon('heroicon-o-eye')
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
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label('Pengumpulan')
                    ->counts('submissions')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->toggleable(),
                IconColumn::make('is_published')
                    ->label('Publish')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('overdue')
                    ->label('Sudah Lewat Deadline')
                    ->query(fn (Builder $query) => $query->where('deadline', '<', now())),

                Filter::make('this_week')
                    ->label('Deadline Minggu Ini')
                    ->query(fn (Builder $query) => $query->whereBetween('deadline', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                TernaryFilter::make('is_published')
                    ->label('Status Publish'),
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
