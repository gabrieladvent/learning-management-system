<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers;
use App\Models\Classroom;
use App\Models\ClassroomSubject;
use App\Models\Subject;
use App\Models\Teacher;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
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
use Illuminate\Database\Eloquent\Builder;

class CourseResource extends Resource
{
    protected static ?string $model = ClassroomSubject::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Pengajaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Mengajar';

    protected static ?string $pluralModelLabel = 'Mata Pelajaran';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['classroom', 'subject', 'teacher']);

        $user = auth()->user();

        if ($user?->hasRole('super_admin')) {
            return $query;
        }

        if ($user?->teacher) {
            return $query->where('teacher_id', $user->teacher->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Penugasan Mengajar')->schema([
                Select::make('classroom_id')
                    ->label('Kelas')
                    ->options(Classroom::query()->where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->options(Subject::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('teacher_id')
                    ->label('Guru Pengampu')
                    ->options(Teacher::query()->orderBy('full_name')->pluck('full_name', 'id'))
                    ->required()
                    ->searchable()
                    ->default(fn () => auth()->user()?->teacher?->id),

                Select::make('semester')
                    ->label('Semester')
                    ->options([1 => 'Semester 1', 2 => 'Semester 2'])
                    ->required(),

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
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject.code')
                    ->label('Kode')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('teacher.full_name')
                    ->label('Guru')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('semester')
                    ->label('Semester')
                    ->formatStateUsing(fn ($state) => "Semester {$state}")
                    ->alignCenter(),

                TextColumn::make('academic_year')
                    ->label('Tahun Ajaran'),

                TextColumn::make('materials_count')
                    ->label('Materi')
                    ->counts('materials')
                    ->alignCenter(),
            ])
            ->defaultSort('academic_year', 'desc')
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Kelas')
                    ->options(Classroom::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->options(Subject::query()->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('semester')
                    ->label('Semester')
                    ->options([1 => 'Semester 1', 2 => 'Semester 2']),
            ])
            ->actions([
                ViewAction::make()->label('Buka'),
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\MaterialsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'view' => Pages\ViewCourse::route('/{record}'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
