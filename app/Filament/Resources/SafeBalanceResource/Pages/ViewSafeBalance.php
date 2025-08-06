<?php

namespace App\Filament\Resources\SafeBalanceResource\Pages;

use App\Filament\Resources\SafeBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSafeBalance extends ViewRecord
{
    protected static string $resource = SafeBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
