<?php

namespace App\Filament\Widgets;

use App\Services\SafeBalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat; // <-- Make sure to import this

class FinancialOverview extends BaseWidget
{
    // 1. REMOVE the $view property. This was causing the error.
    // protected static string $view = 'filament.widgets.financial-overview';

    protected static ?int $sort = 1;

    // 2. CHANGE getViewData() to getStats() and return an array of Stat objects.
    protected function getStats(): array
    {
        // Fetch your data. Let's assume it returns an array.
        $overviewData = SafeBalanceService::getFinancialOverview();

        // Return an array of Stat objects
        return [
            Stat::make('Unique Visitors', '192.1k')
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Bounce Rate', '21%')
                ->description('7% increase')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            // Example using your data from the service
            // Make sure your service returns the values you need.
            Stat::make('Total Balance', $overviewData['total_balance'] ?? '0')
                ->description('Current total balance')
                ->color('primary'),
        ];
    }
}
