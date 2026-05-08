<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolResource\Pages;
use App\Models\School;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchoolResource extends Resource
{
    protected static ?string $model = School::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Sekolah';

    protected static ?string $pluralModelLabel = 'Sekolah';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nama Sekolah')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            TextInput::make('email')
                ->label('Email Sekolah')
                ->email()
                ->maxLength(255),

            TextInput::make('phone')
                ->label('Telepon Sekolah')
                ->tel()
                ->maxLength(20),

            Textarea::make('address')
                ->label('Alamat Sekolah')
                ->rows(3)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Status Sekolah')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Sekolah')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email Sekolah')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Telepon Sekolah')
                    ->copyable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
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
            'index' => Pages\ListSchools::route('/'),
            'create' => Pages\CreateSchool::route('/create'),
            'view' => Pages\ViewSchool::route('/{record}'),
            'edit' => Pages\EditSchool::route('/{record}/edit'),
        ];
    }
}
