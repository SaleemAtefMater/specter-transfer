<?php

namespace App\Filament\Resources\TransferDeliveryResource\Pages;

use App\Filament\Resources\TransferDeliveryResource;
use App\Services\SafeBalanceService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewTransferDelivery extends ViewRecord
{
    protected static string $resource = TransferDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('deliver_now')
                ->label('Deliver Transfer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size('lg')
                ->requiresConfirmation()
                ->modalHeading('Deliver Transfer Now')
                ->modalDescription(fn () => "Are you sure you want to deliver transfer {$this->record->transfer_number} for {$this->record->customer_name}?")
                ->modalSubmitActionLabel('Deliver Transfer')
                ->form([
                    Forms\Components\Textarea::make('delivery_notes')
                        ->label('Delivery Notes (Optional)')
                        ->placeholder('Add any notes about this delivery...')
                        ->rows(3),
                ])
                ->before(function (array $data, Actions\Action $action) {
                    // Validate balance before delivery
                    $validation = SafeBalanceService::validateTransferAgainstSafeBalance($this->record, 'delivered');

                    if (!$validation['is_valid']) {
                        Notification::make()
                            ->title('Cannot Deliver Transfer')
                            ->body($validation['message'])
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->cancel();
                        return false;
                    }
                })
                ->action(function (array $data) {
                    // Update transfer status and notes
                    $updateData = ['status' => 'delivered'];
                    if (!empty($data['delivery_notes'])) {
                        $currentNotes = $this->record->notes ? $this->record->notes . "\n\n" : '';
                        $updateData['notes'] = $currentNotes . "Delivery Notes: " . $data['delivery_notes'];
                    }

                    $this->record->update($updateData);

                    // Calculate balance impact
                    $balanceChange = $this->record->profit - ($this->record->sent_amount - $this->record->transfer_cost);

                    // Show success notification
                    Notification::make()
                        ->title('Transfer Delivered Successfully!')
                        ->body("Transfer {$this->record->transfer_number} has been delivered. Safe balance changed by " .
                            ($balanceChange >= 0 ? '+' : '') . '$' . number_format($balanceChange, 2))
                        ->success()
                        ->duration(5000)
                        ->send();

                    // Redirect to the deliveries list
                    return redirect()->route('filament.admin.resources.transfer-deliveries.index');
                }),

            Actions\Action::make('cancel_transfer')
                ->label('Cancel Transfer')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel Transfer')
                ->modalDescription(fn () => "Are you sure you want to cancel transfer {$this->record->transfer_number}? This will reverse the balance changes.")
                ->modalSubmitActionLabel('Cancel Transfer')
                ->form([
                    Forms\Components\Textarea::make('cancellation_reason')
                        ->label('Cancellation Reason')
                        ->required()
                        ->placeholder('Please provide a reason for cancellation...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    // Update transfer status and add cancellation notes
                    $currentNotes = $this->record->notes ? $this->record->notes . "\n\n" : '';
                    $this->record->update([
                        'status' => 'canceled',
                        'notes' => $currentNotes . "Canceled: " . $data['cancellation_reason']
                    ]);

                    Notification::make()
                        ->title('Transfer Canceled')
                        ->body("Transfer {$this->record->transfer_number} has been canceled and balance changes have been reversed.")
                        ->warning()
                        ->send();

                    // Redirect to the deliveries list
                    return redirect()->route('filament.admin.resources.transfer-deliveries.index');
                }),

            Actions\Action::make('balance_impact')
                ->label('View Full Balance Impact')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->modalHeading('Complete Balance Impact Analysis')
                ->modalContent(function () {
                    $safeTypeId = $this->record->transfer_type_id;
                    $validation = SafeBalanceService::validateTransferAgainstSafeBalance($this->record, $this->record->status);

                    return view('filament.transfers.balance-impact-detailed', [
                        'transfer' => $this->record,
                        'validation' => $validation,
                        'safe_summary' => SafeBalanceService::getSafeTypeSummary($safeTypeId),
                        'transfer_summary' => SafeBalanceService::getTransferSummaryBySafeType($safeTypeId)
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }

    public function getTitle(): string
    {
        return "Transfer Delivery - {$this->record->transfer_number}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Transfer Information Section
                Infolists\Components\Section::make('Transfer Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('transfer_number')
                                    ->label('Transfer Number')
                                    ->badge()
                                    ->color('primary'),

                                Infolists\Components\TextEntry::make('customer_name')
                                    ->label('Customer Name'),

                                Infolists\Components\TextEntry::make('phone_number')
                                    ->label('Phone Number'),
                            ]),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('transferType.name')
                                    ->label('Transfer Type'),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'checked' => 'warning',
                                        'delivered' => 'success',
                                        'canceled' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ])
                    ->collapsible(),

                // Financial Details Section
                Infolists\Components\Section::make('Financial Details')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('sent_amount')
                                    ->label('Sent Amount')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('transfer_cost')
                                    ->label('Transfer Cost')
                                    ->money('USD')
                                    ->color('danger'),

                                Infolists\Components\TextEntry::make('customer_price')
                                    ->label('Customer Charge')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('receiver_net_amount')
                                    ->label('Receiver Amount')
                                    ->money('USD'),
                            ]),
                    ])
                    ->collapsible(),

                // Balance Impact Analysis Section
                Infolists\Components\Section::make('Balance Impact Analysis')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('current_safe_balance')
                                    ->label('Current Safe Balance')
                                    ->getStateUsing(function () {
                                        $summary = SafeBalanceService::getSafeTypeSummary($this->record->transfer_type_id);
                                        return '$' . number_format($summary['current_balance'] ?? 0, 2);
                                    })
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('safe_type_name')
                                    ->label('Safe Type')
                                    ->getStateUsing(fn () => $this->record->transferType?->name ?? 'N/A'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('current_amount_in_safe')
                                    ->label('Currently Added (Checked)')
                                    ->getStateUsing(function () {
                                        $amount = $this->record->sent_amount - $this->record->transfer_cost;
                                        return '$' . number_format($amount, 2);
                                    })
                                    ->color('warning'),

                                Infolists\Components\TextEntry::make('profit_on_delivery')
                                    ->label('Profit (After Delivery)')
                                    ->getStateUsing(fn () => '$' . number_format($this->record->profit, 2))
                                    ->color('success'),

                                Infolists\Components\TextEntry::make('net_change')
                                    ->label('Net Balance Change')
                                    ->getStateUsing(function () {
                                        $currentAdded = $this->record->sent_amount - $this->record->transfer_cost;
                                        $profit = $this->record->profit;
                                        $netChange = $profit - $currentAdded;
                                        return ($netChange >= 0 ? '+' : '') . '$' . number_format($netChange, 2);
                                    })
                                    ->color(function () {
                                        $currentAdded = $this->record->sent_amount - $this->record->transfer_cost;
                                        $profit = $this->record->profit;
                                        $netChange = $profit - $currentAdded;
                                        return $netChange >= 0 ? 'success' : 'danger';
                                    }),
                            ]),

                        Infolists\Components\TextEntry::make('balance_after_delivery')
                            ->label('Safe Balance After Delivery')
                            ->getStateUsing(function () {
                                $summary = SafeBalanceService::getSafeTypeSummary($this->record->transfer_type_id);
                                $currentBalance = $summary['current_balance'] ?? 0;

                                $currentAdded = $this->record->sent_amount - $this->record->transfer_cost;
                                $profit = $this->record->profit;
                                $balanceChange = $profit - $currentAdded;

                                $finalBalance = $currentBalance + $balanceChange;
                                return '$' . number_format($finalBalance, 2);
                            })
                            ->color(function () {
                                $summary = SafeBalanceService::getSafeTypeSummary($this->record->transfer_type_id);
                                $currentBalance = $summary['current_balance'] ?? 0;

                                $currentAdded = $this->record->sent_amount - $this->record->transfer_cost;
                                $profit = $this->record->profit;
                                $balanceChange = $profit - $currentAdded;

                                $finalBalance = $currentBalance + $balanceChange;
                                return $finalBalance >= 0 ? 'success' : 'danger';
                            })
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                    ])
                    ->collapsible(false),

                // Additional Information Section
                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes available')
                            ->columnSpanFull(),

                        Infolists\Components\ImageEntry::make('transfer_photo')
                            ->label('Transfer Photo')
                            ->disk('private')
                            ->visibility('private')
                            ->columnSpanFull(),

                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
