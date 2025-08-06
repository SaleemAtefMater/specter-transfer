<?php

namespace App\Filament\Widgets;

use App\Models\DebtPayment;
use App\Services\SafeBalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SafeBalanceOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalSafeBalance = SafeBalanceService::getTotalSafeBalance();
        $debtStats = SafeBalanceService::getDebtStatistics();
        $totalPaymentsToday = DebtPayment::whereDate('payment_date', today())->sum('payment_amount');

        return [
            Stat::make('Total Safe Balance', '$' . number_format($totalSafeBalance, 2))
                ->description('Combined balance across all safes')
                ->icon('heroicon-o-banknotes')
                ->color($totalSafeBalance >= 0 ? 'success' : 'danger'),

            Stat::make('Total Debts', '$' . number_format($debtStats['total_debts'], 2))
                ->description('All outstanding debts')
                ->icon('heroicon-o-credit-card')
                ->color('warning'),

            Stat::make('Unpaid Debts', '$' . number_format($debtStats['unpaid_debts'], 2))
                ->description($debtStats['unpaid_customers'] . ' customers with unpaid debts')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make('Payments Today', '$' . number_format($totalPaymentsToday, 2))
                ->description('Debt payments received today')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
        ];
    }
}
