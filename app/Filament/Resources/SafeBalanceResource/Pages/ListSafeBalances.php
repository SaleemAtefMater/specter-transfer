<?php

namespace App\Filament\Resources\SafeBalanceResource\Pages;

use App\Filament\Resources\SafeBalanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSafeBalances extends ListRecords
{
    protected static string $resource = SafeBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\SafeBalanceOverview::class,
        ];
    }
}
