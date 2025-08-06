<?php

namespace App\Filament\Widgets;

use App\Models\SafeBalance;
use Filament\Widgets\ChartWidget;

class SafeBalanceChart extends ChartWidget
{
    protected static ?string $heading = 'Safe Balance Distribution';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $safeBalances = SafeBalance::with('safeType')
            ->whereHas('safeType', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($safeBalances->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Safe Balances',
                        'data' => [0],
                        'backgroundColor' => ['#E5E7EB'],
                    ],
                ],
                'labels' => ['No Data'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Safe Balances',
                    'data' => $safeBalances->pluck('current_balance')->toArray(),
                    'backgroundColor' => [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#C9CBCF'
                    ],
                ],
            ],
            'labels' => $safeBalances->pluck('safeType.name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
