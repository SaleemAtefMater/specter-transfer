<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Models\SafeType;
use App\Models\Transfer;
use App\Models\TransferType;
use App\Services\SafeBalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Transfers';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('transfer_type_id')
                            ->label('Transfer Type')
                            ->options(SafeType::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Show current safe balance when transfer type is selected
                                if ($state) {
                                    $balance = SafeBalanceService::getSafeTypeSummary($state);
                                    $set('safe_balance_info', $balance ? $balance['current_balance'] : 0);
                                }
                            }),

                        Forms\Components\TextInput::make('transfer_number')
                            ->label('Transfer Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated'),
                    ]),

                // Safe Balance Information (read-only)
                Forms\Components\Section::make('Safe Balance Information')
                    ->schema([
                        Forms\Components\Placeholder::make('safe_balance_display')
                            ->label('Current Safe Balance')
                            ->content(function (Get $get, $record) {
                                $safeTypeId = $get('transfer_type_id');
                                if ($safeTypeId) {
                                    $summary = SafeBalanceService::getSafeTypeSummary($safeTypeId);
                                    return $summary ? '$' . number_format($summary['current_balance'], 2) : '$0.00';
                                }
                                return 'Select transfer type to see balance';
                            }),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('customer_name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone_number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20),
                            ]),
                    ]),

                Forms\Components\Section::make('Transfer Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('sent_amount')
                                    ->label('Sent Amount')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::calculateReceiverAmount($get, $set);
                                        self::updateProjectedBalance($get, $set);
                                    }),

                                Forms\Components\TextInput::make('transfer_cost')
                                    ->label('Transfer Cost (Our Cost)')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::calculateReceiverAmount($get, $set);
                                        self::updateProjectedBalance($get, $set);
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('customer_price')
                                    ->label('Customer Price (Charge)')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::calculateReceiverAmount($get, $set);
                                        self::updateProjectedBalance($get, $set);
                                    }),

                                Forms\Components\TextInput::make('receiver_net_amount')
                                    ->label('Receiver Net Amount')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::updateProjectedBalance($get, $set);
                                    }),
                            ]),

                        // Balance Impact Preview
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Placeholder::make('profit_preview')
                                    ->label('Profit (when delivered)')
                                    ->content(function (Get $get) {
                                        $sent = (float) $get('sent_amount');
                                        $cost = (float) $get('transfer_cost');
                                        $customerPrice = (float) $get('customer_price');
                                        $profit = ($sent - $cost) - $customerPrice ;
                                        return '$' . number_format($profit, 2);
                                    }),

                                Forms\Components\Placeholder::make('checked_amount')
                                    ->label('Amount added when checked')
                                    ->content(function (Get $get) {
                                        $sent = (float) $get('sent_amount');
                                        $cost = (float) $get('transfer_cost');
                                        $amount = $sent - $cost;
                                        return '$' . number_format($amount, 2);
                                    }),

                                Forms\Components\Placeholder::make('delivered_amount')
                                    ->label('Amount added when delivered')
                                    ->content(function (Get $get) {
                                        $sent = (float) $get('sent_amount');
                                        $cost = (float) $get('transfer_cost');
                                        $receiver = (float) $get('receiver_net_amount');
                                        $amount = $sent - $receiver;
                                        return '$' . number_format($amount, 2);
                                    }),
                            ]),
                    ]),

                Forms\Components\Section::make('Status & Additional Info')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'checked' => 'Checked (Check-in only)',
                                        'delivered' => 'Delivered',
                                        'canceled' => 'Canceled',
                                    ])
                                    ->default('checked')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state, $record) {
                                        // Show balance validation when status changes
                                        if ($record && $state !== $record->status) {
                                            $validation = SafeBalanceService::validateTransferAgainstSafeBalance($record, $state);
                                            if (!$validation['is_valid']) {
                                                Notification::make()
                                                    ->title('Balance Warning')
                                                    ->body($validation['message'])
                                                    ->warning()
                                                    ->send();
                                            }
                                        }
                                    }),

                                Forms\Components\FileUpload::make('transfer_photo')
                                    ->label('Transfer Photo')
                                    ->image()
                                    ->directory('transfer-photos')
                                    ->visibility('private'),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function calculateReceiverAmount(Get $get, Set $set): void
    {
        $sentAmount = (float) $get('sent_amount');
        $customerPrice = (float) $get('customer_price');

        if ($sentAmount && $customerPrice) {
            $receiverAmount = $sentAmount - $customerPrice;
            $set('receiver_net_amount', number_format($receiverAmount, 2, '.', ''));
        }
    }

    protected static function updateProjectedBalance(Get $get, Set $set): void
    {
        $safeTypeId = $get('transfer_type_id');
        if (!$safeTypeId) return;

        $summary = SafeBalanceService::getSafeTypeSummary($safeTypeId);
        $currentBalance = $summary ? $summary['current_balance'] : 0;

        $sent = (float) $get('sent_amount');
        $cost = (float) $get('transfer_cost');
        $checkedAmount = $sent - $cost;

        $set('safe_balance_info', $currentBalance);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transferType.name')
                    ->label('Type')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone_number')
                    ->searchable(),

                Tables\Columns\TextColumn::make('sent_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transfer_cost')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('receiver_net_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'checked',
                        'success' => 'delivered',
                        'danger' => 'canceled',
                    ]),

                Tables\Columns\TextColumn::make('profit')
                    ->label('Profit/Loss')
                    ->money('USD')
                    ->getStateUsing(function ($record) {
                        return $record->profit;
                    })
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('safe_amount')
                    ->label('Safe Impact')
                    ->money('USD')
                    ->getStateUsing(function ($record) {
                        return $record->safe_amount;
                    })
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->tooltip('Amount added to/removed from safe'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('transfer_type_id')
                    ->label('Transfer Type')
                    ->options(TransferType::pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'checked' => 'Checked',
                        'delivered' => 'Delivered',
                        'canceled' => 'Canceled',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_balance_impact')
                    ->label('Balance Impact')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalHeading('Transfer Balance Impact')
                    ->modalContent(function ($record) {
                        $safeTypeId = $record->transfer_type_id;
                        $validation = SafeBalanceService::validateTransferAgainstSafeBalance($record, $record->status);

                        return view('filament.transfers.balance-impact', [
                            'transfer' => $record,
                            'validation' => $validation,
                            'safe_summary' => SafeBalanceService::getSafeTypeSummary($safeTypeId)
                        ]);
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        // Validate balance before deletion
                        $validation = SafeBalanceService::validateTransferAgainstSafeBalance($record, 'canceled');
                        if (!$validation['is_valid']) {
                            Notification::make()
                                ->title('Cannot Delete Transfer')
                                ->body($validation['message'])
                                ->danger()
                                ->send();
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransfers::route('/'),
            'create' => Pages\CreateTransfer::route('/create'),
            'view' => Pages\ViewTransfer::route('/{record}'),
            'edit' => Pages\EditTransfer::route('/{record}/edit'),
        ];
    }
}
