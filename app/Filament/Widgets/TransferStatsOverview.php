<?php

namespace App\Filament\Widgets;

use App\Models\Transfer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransferStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTransfers = Transfer::count();
        $totalAmount = Transfer::sum('sent_amount');
        $totalProfit = Transfer::get()->sum('profit');
        $deliveredTransfers = Transfer::where('status', 'delivered')->count();

        return [
            Stat::make('Total Transfers', $totalTransfers)
                ->description('All time transfers')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Total Amount', '$' . number_format($totalAmount, 2))
                ->description('Total money transferred')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),

            Stat::make('Total Profit', '$' . number_format($totalProfit, 2))
                ->description('Net profit/loss')
                ->icon('heroicon-o-chart-bar')
                ->color($totalProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Delivered', $deliveredTransfers)
                ->description('Successfully delivered')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
