<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferResource\Pages;
use App\Filament\Resources\TransferResource\RelationManagers;
use App\Models\Transfer;
use App\Models\TransferType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set;
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
                            ->options(TransferType::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('transfer_number')
                            ->label('Transfer Number')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated'),
                    ]),

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
//                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
//                                        self::calculateReceiverAmount($get, $set);
//                                    }
                                    ,

                                Forms\Components\TextInput::make('transfer_cost')
                                    ->label('Transfer Cost (Our Cost)')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('customer_price')
                                    ->label('Customer Price (Charge)')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->live(onBlur: true)
//                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
//                                        self::calculateReceiverAmount($get, $set);
//                                    })
                                ,

                                Forms\Components\TextInput::make('receiver_net_amount')
                                    ->label('Receiver Net Amount')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                            ]),
                    ]),

                Forms\Components\Section::make('Status & Additional Info')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'checked' => 'Checked',
                                        'delivered' => 'Delivered',
                                        'canceled' => 'Canceled',
                                    ])
                                    ->default('checked')
                                    ->required(),

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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
        return [
            //
        ];
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
