<?php

namespace App\Filament\Resources\TransferTypeResource\Pages;

use App\Filament\Resources\TransferTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferTypes extends ListRecords
{
    protected static string $resource = TransferTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
