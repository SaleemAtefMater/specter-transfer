<?php

namespace App\Filament\Widgets;

use App\Models\Transfer;
use App\Services\SafeBalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingDeliveriesStats extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingTransfers = Transfer::where('status', 'checked')->get();
        $pendingCount = $pendingTransfers->count();
        $totalPendingAmount = $pendingTransfers->sum('sent_amount');
        $totalPendingCost = $pendingTransfers->sum('transfer_cost');
        $expectedProfit = $pendingTransfers->sum('profit');
        $currentlyInSafe = $totalPendingAmount - $totalPendingCost;
        $totalSafeBalance = SafeBalanceService::getTotalSafeBalance();

        return [
            Stat::make('Pending Deliveries', $pendingCount)
                ->description($pendingCount > 10 ? 'High volume - prioritize delivery' : 'Normal volume')
                ->descriptionIcon($pendingCount > 10 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($pendingCount > 10 ? 'danger' : ($pendingCount > 5 ? 'warning' : 'success'))
                ->chart([7, 12, 8, 15, 9, $pendingCount]),

            Stat::make('Total Pending Value', '$' . number_format($totalPendingAmount, 2))
                ->description('Amount waiting for delivery')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Expected Profit', '$' . number_format($expectedProfit, 2))
                ->description('Profit when all delivered')
                ->descriptionIcon($expectedProfit >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($expectedProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Total Safe Balance', '$' . number_format($totalSafeBalance, 2))
                ->description('Current total across all safes')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
