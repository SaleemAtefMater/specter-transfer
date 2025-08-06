<?php

namespace App\Filament\Resources\TransferTypeResource\Pages;

use App\Filament\Resources\TransferTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransferType extends ViewRecord
{
    protected static string $resource = TransferTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
