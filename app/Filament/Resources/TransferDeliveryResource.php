<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferDeliveryResource\Pages;
use App\Models\Transfer;
use App\Models\SafeType;
use App\Services\SafeBalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class TransferDeliveryResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Transfer Deliveries';
    protected static ?string $modelLabel = 'Transfer Delivery';
    protected static ?string $pluralModelLabel = 'Transfer Deliveries';
    protected static ?string $navigationGroup = 'Transfers';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // Show pending, checked, AND partially delivered transfers
        return parent::getEloquentQuery()->whereIn('status', [
            'pending_verification',
            'checked',
            'partially_delivered'  // Include partial deliveries
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Transfers ready for delivery')
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('transferType.name')
                    ->label('Transfer Type')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('sent_amount')
                    ->label('Sent Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receiver_net_amount')
                    ->label('Suggested Delivery')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('in_safe_amount')
                    ->label('Currently in Safe')
                    ->getStateUsing(function ($record) {
                        return $record->sent_amount - $record->transfer_cost;
                    })
                    ->money('USD')
                    ->color('warning'),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('USD')
                    ->sortable()
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return $record && $record->status === 'partially_delivered' ? $record->remaining_amount : null;
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'info' => 'pending_verification',
                        'warning' => 'checked',
                        'orange' => 'partially_delivered',  // New color
                        'success' => 'delivered',
                        'danger' => 'canceled',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('transfer_type_id')
                    ->label('Transfer Type')
                    ->options(SafeType::pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_verification' => 'Pending Verification',
                        'checked' => 'Checked',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('deliver')
                    ->label('Deliver Transfer')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Forms\Components\Placeholder::make('transfer_info')
                            ->label('Transfer Details')
                            ->content(function ($record) {
                                return "Customer: {$record->customer_name}\n" .
                                    "Amount: \${$record->sent_amount}\n" .
                                    "Suggested Delivery: \${$record->receiver_net_amount}\n" .
                                    "Currently in Safe: \$" . number_format($record->sent_amount - $record->transfer_cost, 2);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Select::make('delivery_safe_type_id')
                            ->label('Pay From Safe')
                            ->options(function () {
                                return SafeType::where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(function ($safe) {
                                        $balance = SafeBalanceService::getSafeBalance($safe->id);
                                        $status = $balance > 0 ? '✅' : '⚠️';
                                        return [$safe->id => "{$status} {$safe->name} (\${$balance})"];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, $record) {
                                if ($state && $record) {
                                    $safe = SafeType::find($state);
                                    $balance = SafeBalanceService::getSafeBalance($state);
                                    $suggestedAmount = $record->receiver_net_amount;

                                    if ($balance < $suggestedAmount) {
                                        Notification::make()
                                            ->title('Balance Warning')
                                            ->body("Insufficient balance in {$safe->name}. Current: \${$balance}, Needed: \${$suggestedAmount}")
                                            ->warning()
                                            ->duration(5000)
                                            ->send();
                                    }
                                }
                            }),

                        Forms\Components\TextInput::make('delivery_amount')
                            ->label('Delivery Amount')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->default(fn ($record) => $record->receiver_net_amount)
                            ->live(onBlur: true),

                        Forms\Components\Textarea::make('delivery_notes')
                            ->label('Delivery Notes (Optional)')
                            ->placeholder('Add any notes about this delivery...')
                            ->rows(3),

                        Forms\Components\Placeholder::make('delivery_preview')
                            ->label('Delivery Preview')
                            ->content(function (Forms\Get $get, $record) {
                                $deliveryAmount = (float) $get('delivery_amount');
                                $transferAmount = $record->sent_amount - $record->transfer_cost;
                                $profit = $transferAmount - $deliveryAmount;

                                return "Transfer Amount in Safe: \${$transferAmount}\n" .
                                    "Will Pay Out: \${$deliveryAmount}\n" .
                                    "Expected Profit: " . ($profit >= 0 ? '+' : '') . "\${$profit}";
                            })
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            Log::info("Starting delivery action", [
                                'transfer_id' => $record->id,
                                'delivery_safe_type_id' => $data['delivery_safe_type_id'],
                                'delivery_amount' => $data['delivery_amount']
                            ]);

                            // Use the transfer model's safe delivery method
                            $result = $record->deliverSafely(
                                $data['delivery_safe_type_id'],
                                (float) $data['delivery_amount'],
                                $data['delivery_notes'] ?? null
                            );

                            if ($result['success']) {
                                Notification::make()
                                    ->title('Transfer Delivered Successfully!')
                                    ->body("Profit: \$" . number_format($result['profit'], 2))
                                    ->success()
                                    ->duration(5000)
                                    ->send();

                                Log::info("Transfer delivered successfully", $result);
                            } else {
                                Notification::make()
                                    ->title('Delivery Failed')
                                    ->body($result['message'])
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                Log::error("Transfer delivery failed", $result);
                            }
                        } catch (\Exception $e) {
                            Log::error("Exception during transfer delivery", [
                                'transfer_id' => $record->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);

                            Notification::make()
                                ->title('Delivery Error')
                                ->body('An unexpected error occurred. Please check the logs.')
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make()
                    ->label('Review Details'),

                Tables\Actions\Action::make('cancel_transfer')
                    ->label('Cancel Transfer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // Remove the transfer amount from the safe first
                            $transferAmount = $record->sent_amount - $record->transfer_cost;
                            if ($transferAmount > 0) {
                                SafeBalanceService::updateSafeBalance($record->transfer_type_id, $transferAmount, 'subtract');
                                SafeBalanceService::createBalanceTransaction(
                                    $record->transfer_type_id,
                                    'transfer_out',
                                    $transferAmount,
                                    "Transfer canceled - {$record->transfer_number}: {$data['cancellation_reason']}",
                                    'App\Models\Transfer',
                                    $record->id
                                );
                            }

                            // Update transfer record
                            $currentNotes = $record->notes ? $record->notes . "\n\n" : '';
                            $record->update([
                                'status' => 'canceled',
                                'notes' => $currentNotes . "Canceled: " . $data['cancellation_reason']
                            ]);

                            Notification::make()
                                ->title('Transfer Canceled')
                                ->body("Transfer {$record->transfer_number} has been canceled and balance changes have been reversed.")
                                ->warning()
                                ->send();

                        } catch (\Exception $e) {
                            Log::error("Error canceling transfer", [
                                'transfer_id' => $record->id,
                                'error' => $e->getMessage()
                            ]);

                            Notification::make()
                                ->title('Cancellation Error')
                                ->body('Failed to cancel transfer. Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransferDeliveries::route('/'),
            'view' => Pages\ViewTransferDelivery::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', ['pending_verification', 'checked'])->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $count = static::getNavigationBadge();

        if ($count > 10) {
            return 'danger';
        } elseif ($count > 5) {
            return 'warning';
        } elseif ($count > 0) {
            return 'success';
        }

        return 'gray';
    }
}
