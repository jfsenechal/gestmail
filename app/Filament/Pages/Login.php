<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login as BasePage;

class Login extends BasePage
{
    public function mount(): void
    {
        parent::mount();
        if (! app()->environment('production')) {
            $this->form->fill([
                'email' => config('mail.webmaster_email'),
                'password' => 'marge',
                'remember' => true,
            ]);
        }
    }
}
