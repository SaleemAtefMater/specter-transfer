<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\Report;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReportResource extends Resource
{
    // This is a special resource without a model since we're dealing with reports
    protected static ?string $model = null;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Reports & Analytics';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Financial Reports';
    protected static ?string $slug = 'reports';

//    public static function getPages(): array
//    {
//        return [
//            'index' => \App\Filament\Pages\Reports\FinancialReports::route('/'),
//            'monthly' => \App\Filament\Pages\Reports\MonthlyReport::route('/monthly'),
//            'profit-loss' => \App\Filament\Pages\Reports\ProfitLossReport::route('/profit-loss'),
//            'debt-analysis' => \App\Filament\Pages\Reports\DebtAnalysisReport::route('/debt-analysis'),
//            'customer-activity' => \App\Filament\Pages\Reports\CustomerActivityReport::route('/customer-activity'),
//            'daily-summary' => \App\Filament\Pages\Reports\DailySummaryReport::route('/daily-summary'),
//        ];
//    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
        ];
    }

    // Disable standard CRUD operations since this is a reports-only resource
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
