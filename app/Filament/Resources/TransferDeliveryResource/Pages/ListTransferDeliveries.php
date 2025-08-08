<?php
namespace App\Filament\Resources\TransferDeliveryResource\Pages;

use App\Filament\Resources\TransferDeliveryResource;
use App\Filament\Widgets\PendingDeliveriesStats;
use App\Models\Transfer;
use App\Services\SafeBalanceService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransferDeliveries extends ListRecords
{
    protected static string $resource = TransferDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh_balances')
                ->label('Refresh Balances')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    // This will refresh the page to show updated balance information
                    $this->redirect(request()->header('Referer'));
                })
                ->color('gray'),

            Actions\Action::make('safe_overview')
                ->label('Safe Balance Overview')
                ->icon('heroicon-o-banknotes')
                ->modalHeading('Current Safe Balances')
                ->modalContent(function () {
                    $safeTypes = SafeBalanceService::getSafeTypesWithBalances();
                    $totalBalance = SafeBalanceService::getTotalSafeBalance();

                    return view('filament.transfers.safe-overview', [
                        'safe_types' => $safeTypes,
                        'total_balance' => $totalBalance
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->color('success'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Pending Deliveries')
                ->badge($this->getTableQuery()->count()),

            'high_value' => Tab::make('High Value (>$500)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('sent_amount', '>', 500))
                ->badge($this->getTableQuery()->where('sent_amount', '>', 500)->count()),

            'recent' => Tab::make('Last 24 Hours')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '>=', now()->subDay()))
                ->badge($this->getTableQuery()->where('created_at', '>=', now()->subDay())->count()),

            'urgent' => Tab::make('Urgent (>3 Days)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '<=', now()->subDays(3)))
                ->badge($this->getTableQuery()->where('created_at', '<=', now()->subDays(3))->count())
                ->badgeColor('danger'),
        ];
    }

    public function getTitle(): string
    {
        return 'Transfer Deliveries';
    }

    public function getHeading(): string
    {
        $count = $this->getTableQuery()->count();
        return "Transfer Deliveries ({$count} pending)";
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PendingDeliveriesStats::class
        ];
    }
}
