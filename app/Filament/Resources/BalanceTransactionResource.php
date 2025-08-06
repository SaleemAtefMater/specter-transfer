<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BalanceTransactionResource\Pages;
use App\Filament\Resources\BalanceTransactionResource\RelationManagers;
use App\Models\BalanceTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BalanceTransactionResource extends Resource
{
    protected static ?string $model = BalanceTransaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';
    protected static ?string $navigationGroup = 'Safe Management';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Transaction History';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('transaction_number')
                    ->disabled(),
                Forms\Components\Select::make('safe_type_id')
                    ->relationship('safeType', 'name')
                    ->disabled(),
                Forms\Components\Select::make('type')
                    ->options([
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'debt_payment' => 'Debt Payment',
                    ])
                    ->disabled(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->disabled(),
                Forms\Components\Textarea::make('description')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('safeType.name')
                    ->label('Safe Type')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => ['deposit', 'transfer_in'],
                        'danger' => ['withdrawal', 'transfer_out', 'debt_payment'],
                    ]),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('safe_type_id')
                    ->relationship('safeType', 'name')
                    ->label('Safe Type'),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'deposit' => 'Deposit',
                        'withdrawal' => 'Withdrawal',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'debt_payment' => 'Debt Payment',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBalanceTransactions::route('/'),
            'view' => Pages\ViewBalanceTransaction::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Transactions are created automatically
    }
}
