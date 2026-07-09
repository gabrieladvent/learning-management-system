<?php

namespace App\Filament\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public function getTitle(): string
    {
        return 'Profil Saya';
    }

    public function getHeading(): string
    {
        return 'Profil Saya';
    }

    public function getSubheading(): ?string
    {
        return 'Perbarui foto profil, email, dan password akunmu.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Foto & Informasi Akun')
                    ->description('Foto ini tampil di menu akun dan seluruh panel.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        $this->getAvatarFormComponent(),
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),
                Section::make('Keamanan')
                    ->description('Kosongkan bagian password bila tidak ingin menggantinya.')
                    ->icon('heroicon-o-lock-closed')
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ]),
            ]);
    }

    protected function getAvatarFormComponent(): Component
    {
        return SpatieMediaLibraryFileUpload::make('avatar')
            ->label('Foto Profil')
            ->collection('avatar')
            ->avatar()
            ->image()
            ->imageEditor()
            ->circleCropper()
            ->maxSize(2048)
            ->helperText('Format PNG/JPG/WEBP, maksimal 2MB.')
            ->columnSpanFull();
    }
}
