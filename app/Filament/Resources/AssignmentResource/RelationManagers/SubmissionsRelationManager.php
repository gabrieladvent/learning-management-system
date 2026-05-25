<?php

namespace App\Filament\Resources\AssignmentResource\RelationManagers;

use Carbon\CarbonInterface;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
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
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Pengumpulan Siswa';

    protected static ?string $modelLabel = 'Pengumpulan';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Jawaban Siswa')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Placeholder::make('submitted_at_view')
                        ->label('Dikumpulkan')
                        ->content(function ($record) {
                            if (! $record?->submitted_at) {
                                return '—';
                            }

                            $text = $record->submitted_at->translatedFormat('l, d F Y · H:i');
                            $deadline = $record->assignment?->deadline;
                            $isLate = $deadline && $record->submitted_at->greaterThan($deadline);

                            if ($isLate) {
                                $late = $record->submitted_at->diffForHumans(
                                    $deadline,
                                    ['parts' => 2, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]
                                );

                                return new HtmlString(
                                    e($text)
                                    .' <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Terlambat '.e($late).'</span>'
                                );
                            }

                            return $text;
                        }),

                    Placeholder::make('content_view')
                        ->label('Esai / Jawaban Teks')
                        ->content(fn ($record) => new HtmlString(
                            '<div class="prose dark:prose-invert max-w-none">'
                            .($record?->content ?: '<em class="text-gray-500">Tidak ada teks</em>')
                            .'</div>'
                        ))
                        ->columnSpanFull(),

                    Placeholder::make('link_view')
                        ->label('Tautan Referensi')
                        ->content(fn ($record) => $record?->link_url
                            ? new HtmlString(
                                '<a href="'.e($record->link_url).'" target="_blank" rel="noopener" '
                                .'class="text-primary-600 hover:text-primary-700 underline break-all">'
                                .e($record->link_url).'</a>'
                            )
                            : '—')
                        ->visible(fn ($record) => filled($record?->link_url))
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('submission_files')
                        ->collection('submission_files')
                        ->label('Lampiran Siswa')
                        ->disabled()
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record?->getMedia('submission_files')->isNotEmpty()),
                ])
                ->collapsible(),

            Section::make('Penilaian')
                ->icon('heroicon-o-star')
                ->schema([
                    TextInput::make('score')
                        ->label('Nilai')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(fn () => $this->getOwnerRecord()->max_score)
                        ->suffix(fn () => '/ '.$this->getOwnerRecord()->max_score),

                    Textarea::make('feedback')
                        ->label('Feedback / Catatan untuk Siswa')
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Riwayat Aktivitas')
                ->icon('heroicon-o-clock')
                ->collapsed()
                ->schema([
                    Placeholder::make('activity_log')
                        ->label('')
                        ->content(fn ($record) => static::renderActivityLog($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function renderActivityLog($record): HtmlString
    {
        if (! $record) {
            return new HtmlString('<em class="text-gray-500">—</em>');
        }

        $activities = Activity::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->with('causer')
            ->latest()
            ->limit(20)
            ->get();

        if ($activities->isEmpty()) {
            return new HtmlString('<em class="text-gray-500">Belum ada aktivitas.</em>');
        }

        $html = '<ul class="space-y-2">';
        foreach ($activities as $activity) {
            $when = $activity->created_at->translatedFormat('d M Y · H:i');
            $causer = $activity->causer;
            $who = $causer
                ? e($causer->name ?? $causer->full_name ?? 'Anonim')
                : 'Sistem';
            $changes = $activity->properties?->get('attributes') ?? [];
            $changeList = collect($changes)
                ->map(fn ($v, $k) => "$k → ".(is_scalar($v) ? $v : json_encode($v)))
                ->implode(', ');

            $html .= '<li class="flex gap-3 p-2 border rounded-md dark:border-gray-700">';
            $html .= '<div class="flex-1">';
            $html .= '<div class="text-sm font-medium">'.e(ucfirst($activity->event ?? '—')).' oleh '.$who.'</div>';
            $html .= '<div class="text-xs text-gray-500">'.e($changeList !== '' ? $changeList : '—').'</div>';
            $html .= '</div>';
            $html .= '<div class="text-xs text-gray-400 whitespace-nowrap">'.e($when).'</div>';
            $html .= '</li>';
        }
        $html .= '</ul>';

        return new HtmlString($html);
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

                TextColumn::make('lateness')
                    ->label('Ketepatan')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (! $record->submitted_at) {
                            return null;
                        }

                        $deadline = $record->assignment?->deadline;

                        if (! $deadline) {
                            return 'Tepat Waktu';
                        }

                        return $record->submitted_at->greaterThan($deadline) ? 'Terlambat' : 'Tepat Waktu';
                    })
                    ->color(fn ($state) => $state === 'Terlambat' ? 'danger' : 'success')
                    ->tooltip(function ($record) {
                        $deadline = $record->assignment?->deadline;

                        if (! $record->submitted_at || ! $deadline || ! $record->submitted_at->greaterThan($deadline)) {
                            return null;
                        }

                        return 'Terlambat '.$record->submitted_at->diffForHumans(
                            $deadline,
                            ['parts' => 2, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]
                        );
                    })
                    ->placeholder('—'),

                IconColumn::make('submitted_at')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn ($record) => filled($record->submitted_at)),

                TextColumn::make('link_url')
                    ->label('Link')
                    ->limit(30)
                    ->url(fn ($state) => $state, true)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('score')
                    ->label('Nilai')
                    ->alignCenter()
                    ->placeholder('—'),

                TextColumn::make('graded_at')
                    ->label('Dinilai')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Belum dinilai')
                    ->since()
                    ->tooltip(fn ($record) => $record->graded_at?->format('d F Y H:i'))
                    ->toggleable(),

                TextColumn::make('feedback')
                    ->label('Feedback')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('submitted_at')
                    ->label('Status Pengumpulan')
                    ->nullable()
                    ->trueLabel('Sudah mengumpulkan')
                    ->falseLabel('Belum mengumpulkan'),

                TernaryFilter::make('score')
                    ->label('Sudah Dinilai')
                    ->nullable()
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('score'),
                        false: fn ($query) => $query->whereNull('score'),
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('Beri Nilai'),
                ]),
            ]);
    }
}
