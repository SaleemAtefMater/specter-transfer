<?php

namespace App\Filament\Resources\SafeTypeResource\Pages;

use App\Filament\Resources\SafeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSafeTypes extends ListRecords
{
    protected static string $resource = SafeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
