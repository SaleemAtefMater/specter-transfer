<?php

namespace App\Filament\Resources\SafeBalanceResource\Pages;

use App\Filament\Resources\SafeBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSafeBalance extends EditRecord
{
    protected static string $resource = SafeBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
