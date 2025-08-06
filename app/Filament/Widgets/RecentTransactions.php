<?php

namespace App\Filament\Widgets;

use App\Models\BalanceTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTransactions extends BaseWidget
{
    protected static ?string $heading = 'Recent Transactions';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BalanceTransaction::query()
                    ->with(['safeType'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->label('Transaction #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('safeType.name')
                    ->label('Safe Type'),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => ['deposit', 'transfer_in'],
                        'danger' => ['withdrawal', 'transfer_out', 'debt_payment'],
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (BalanceTransaction $record): string =>
                    route('filament.admin.resources.balance-transactions.view', $record)
                    )
                    ->icon('heroicon-m-eye'),
            ]);
    }
}
