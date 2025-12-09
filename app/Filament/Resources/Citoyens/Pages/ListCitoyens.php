<?php

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Ldap\LdapCitoyenRepository;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\Request;

class ListCitoyens extends ListRecords
{
    protected static string $resource = CitoyenResource::class;

    public function __construct(private readonly LdapCitoyenRepository $ldap)
    {
    }

    public function mount(): void
    {
        $this->ldap->getAll();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
