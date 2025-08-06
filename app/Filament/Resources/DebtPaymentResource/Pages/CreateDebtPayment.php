<?php

namespace App\Filament\Resources\DebtPaymentResource\Pages;

use App\Filament\Resources\DebtPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDebtPayment extends CreateRecord
{
    protected static string $resource = DebtPaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate payment amount doesn't exceed remaining debt
        $debt = \App\Models\Debt::find($data['debt_id']);
        if ($debt && $data['payment_amount'] > $debt->remaining_amount) {
            throw new \Exception('Payment amount cannot exceed remaining debt amount.');
        }

        return $data;
    }}
