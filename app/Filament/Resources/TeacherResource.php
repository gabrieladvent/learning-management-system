<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherResource\Pages;
use App\Models\Enums\GenderEnum;
use App\Models\Teacher;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Manajemen Pengguna';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Guru';

    protected static ?string $pluralModelLabel = 'Guru';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profil Guru')->schema([
                TextInput::make('full_name')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique('users', 'email', ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Digunakan sebagai akun login guru'),

                TextInput::make('nip')
                    ->label('NIP')
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                TextInput::make('nik')
                    ->label('NIK')
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),

                TextInput::make('specialization')
                    ->label('Spesialisasi / Mapel')
                    ->maxLength(100),

                TextInput::make('phone')
                    ->label('No. Telepon')
                    ->tel()
                    ->maxLength(20),

                Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->options(collect(GenderEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()]))
                    ->required(),

                TextInput::make('place_of_birth')
                    ->label('Tempat Lahir')
                    ->maxLength(100),

                DatePicker::make('birth_date')
                    ->label('Tanggal Lahir')
                    ->native(false)
                    ->displayFormat('d M Y'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('specialization')
                    ->label('Spesialisasi')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Telepon')
                    ->toggleable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('user.is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options(collect(GenderEnum::cases())->mapWithKeys(fn ($e) => [$e->value => $e->label()])),
            ])
            ->actions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('resetPassword')
                        ->label('Reset Password')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reset Password ke Default?')
                        ->modalDescription(fn (Teacher $record) => $record->birth_date
                            ? 'Password akan direset ke password default'
                            : 'Gagal mereset password')
                        ->action(function (Teacher $record) {
                            if (! $record->birth_date || ! $record->user) {
                                Notification::make()
                                    ->title('Gagal mereset password')
                                    ->body('Tanggal lahir atau akun guru tidak ditemukan.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $record->user->update([
                                'password' => Carbon::parse($record->birth_date)->format('dmY'),
                            ]);

                            Notification::make()
                                ->title('Password berhasil direset')
                                ->body("Password direset ke: {$record->birth_date->format('dmY')}")
                                ->success()
                                ->send();
                        }),
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
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'view' => Pages\ViewTeacher::route('/{record}'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}
