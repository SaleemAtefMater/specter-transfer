<?php

namespace App\Filament\Resources\TransferTypeResource\Pages;

use App\Filament\Resources\TransferTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransferType extends EditRecord
{
    protected static string $resource = TransferTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
