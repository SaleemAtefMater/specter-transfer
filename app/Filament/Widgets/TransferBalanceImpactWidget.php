<?php

namespace App\Filament\Resources\TransferDeliveryResource\Widgets;

use App\Models\Transfer;
use App\Services\SafeBalanceService;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class TransferBalanceImpactWidget extends Widget
{
    protected static string $view = 'filament.resources.transfer-delivery-resource.widgets.transfer-balance-impact-widget';

    protected int | string | array $columnSpan = 'full';

    public ?Transfer $record = null;

    public function getViewData(): array
    {
        // Try to get the record from the page if not set
        if (!$this->record) {
            $this->record = $this->getRecord();
        }

        if (!$this->record) {
            return [
                'transfer' => null,
                'error' => 'No transfer record found'
            ];
        }

        $safeTypeId = $this->record->transfer_type_id;
        $validation = SafeBalanceService::validateTransferAgainstSafeBalance($this->record, 'delivered');
        $safeSummary = SafeBalanceService::getSafeTypeSummary($safeTypeId);

        $currentAmountInSafe = $this->record->sent_amount - $this->record->transfer_cost;
        $profitAfterDelivery = $this->record->sent_amount - $this->record->transfer_cost - $this->record->receiver_net_amount;
        $balanceChangeOnDelivery = $profitAfterDelivery - $currentAmountInSafe;

        return [
            'transfer' => $this->record,
            'validation' => $validation,
            'safe_summary' => $safeSummary,
            'current_amount_in_safe' => $currentAmountInSafe,
            'profit_after_delivery' => $profitAfterDelivery,
            'balance_change_on_delivery' => $balanceChangeOnDelivery,
        ];
    }

    protected function getRecord(): ?Transfer
    {
        // Get record from page context
        $livewire = $this->getLivewire();

        // For ViewRecord pages, the record is available as a property
        if (property_exists($livewire, 'record') && $livewire->record instanceof Transfer) {
            return $livewire->record;
        }

        // Alternative: try to get from URL parameter
        $recordId = request()->route('record');
        if ($recordId) {
            return Transfer::find($recordId);
        }

        return null;
    }

    // Method to manually set the record (useful for testing)
    public function setRecord(Transfer $record): void
    {
        $this->record = $record;
    }
}
