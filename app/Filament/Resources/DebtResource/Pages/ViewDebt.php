<?php

namespace App\Filament\Resources\DebtResource\Pages;

use App\Filament\Resources\DebtResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;
class ViewDebt extends ViewRecord
{
    protected static string $resource = DebtResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Debt Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('debt_number'),
                                Components\TextEntry::make('safeType.name')
                                    ->label('Associated Safe'),
                            ]),
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('creditor_name'),
                                Components\TextEntry::make('creditor_phone'),
                            ]),
                    ]),

                Components\Section::make('Financial Details')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('total_amount')
                                    ->money('USD'),
                                Components\TextEntry::make('paid_amount')
                                    ->money('USD')
                                    ->color('success'),
                                Components\TextEntry::make('remaining_amount')
                                    ->money('USD')
                                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                            ]),
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'not_paid' => 'danger',
                                        'partially_paid' => 'warning',
                                        'paid' => 'success',
                                        'canceled' => 'secondary',
                                    }),
                                Components\TextEntry::make('due_date')
                                    ->date(),
                            ]),
                    ]),

                Components\Section::make('Payment History')
                    ->schema([
                        Components\RepeatableEntry::make('payments')
                            ->schema([
                                Components\Grid::make(4)
                                    ->schema([
                                        Components\TextEntry::make('payment_number'),
                                        Components\TextEntry::make('payment_amount')
                                            ->money('USD'),
                                        Components\TextEntry::make('payment_date')
                                            ->date(),
                                        Components\TextEntry::make('safeType.name')
                                            ->label('Paid From'),
                                    ]),
                                Components\TextEntry::make('notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                    ])
                    ->visible(fn ($record) => $record->payments->count() > 0),
            ]);
    }
}
