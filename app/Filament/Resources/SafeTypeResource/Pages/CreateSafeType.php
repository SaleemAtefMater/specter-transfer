<?php

namespace App\Filament\Resources\SafeTypeResource\Pages;

use App\Filament\Resources\SafeTypeResource;
use App\Models\SafeBalance;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSafeType extends CreateRecord
{
    protected static string $resource = SafeTypeResource::class;

    protected function afterCreate(): void
    {
        // Automatically create a balance record for the new safe type
        SafeBalance::create([
            'safe_type_id' => $this->record->id,
            'current_balance' => 0,
            'initial_balance' => 0,
        ]);
    }}
