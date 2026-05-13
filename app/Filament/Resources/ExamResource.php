<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExamResource\Pages;
use App\Models\Enums\ExamStatusEnum;
use App\Models\Exam;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamResource extends Resource
{
    protected static ?string $model = Exam::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Ujian';

    protected static ?string $pluralModelLabel = 'Ujian';

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
                    ->label('Judul Ujian')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y, H:i'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExams::route('/'),
            'edit' => Pages\EditExam::route('/{record}/edit'),
            'view' => Pages\ViewExam::route('/{record}'),
        ];
    }
}
