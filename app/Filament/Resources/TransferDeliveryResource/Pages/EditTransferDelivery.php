<?php

namespace App\Filament\Resources\TransferDeliveryResource\Pages;

use App\Filament\Resources\TransferDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransferDelivery extends EditRecord
{
    protected static string $resource = TransferDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
