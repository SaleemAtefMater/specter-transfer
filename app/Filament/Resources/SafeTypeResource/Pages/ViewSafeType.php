<?php

namespace App\Filament\Resources\SafeTypeResource\Pages;

use App\Filament\Resources\SafeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSafeType extends ViewRecord
{
    protected static string $resource = SafeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
