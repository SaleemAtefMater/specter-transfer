<?php

namespace App\Filament\Widgets;

use App\Models\Debt;
use Filament\Widgets\ChartWidget;

class DebtStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Debt Status Distribution';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $debtCounts = Debt::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        if (empty($debtCounts)) {
            return [
                'datasets' => [
                    [
                        'label' => 'Debts',
                        'data' => [0],
                        'backgroundColor' => ['#E5E7EB'],
                    ],
                ],
                'labels' => ['No Debts'],
            ];
        }

        $statusLabels = [
            'not_paid' => 'Not Paid',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'canceled' => 'Canceled',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Debts',
                    'data' => array_values($debtCounts),
                    'backgroundColor' => [
                        '#FF6384', // Not Paid - Red
                        '#FFCE56', // Partially Paid - Yellow
                        '#4BC0C0', // Paid - Teal
                        '#C9CBCF', // Canceled - Gray
                    ],
                ],
            ],
            'labels' => array_map(fn($status) => $statusLabels[$status] ?? $status, array_keys($debtCounts)),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
