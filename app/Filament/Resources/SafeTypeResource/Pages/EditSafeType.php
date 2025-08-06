<?php

namespace App\Filament\Resources\SafeTypeResource\Pages;

use App\Filament\Resources\SafeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSafeType extends EditRecord
{
    protected static string $resource = SafeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
