<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SafeBalanceResource\Pages;
use App\Filament\Resources\SafeBalanceResource\RelationManagers;
use App\Models\SafeBalance;
use App\Services\SafeBalanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SafeBalanceResource extends Resource
{
    protected static ?string $model = SafeBalance::class;
//    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-right';

    protected static ?string $navigationGroup = 'Safe Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Safe Balances';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('safe_type_id')
                    ->label('Safe Type')
                    ->options(SafeType::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('initial_balance')
                            ->label('Initial Balance')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$get('current_balance')) {
                                    $set('current_balance', $state);
                                }
                            }),

                        Forms\Components\TextInput::make('current_balance')
                            ->label('Current Balance')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->placeholder('Additional notes about this balance'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('safeType.name')
                    ->label('Safe Type')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('safeType.type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'bank',
                        'success' => 'wallet',
                        'warning' => 'cash',
                        'secondary' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('initial_balance')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_balance')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('balance_difference')
                    ->label('Change')
                    ->money('USD')
                    ->getStateUsing(function ($record) {
                        return $record->current_balance - $record->initial_balance;
                    })
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('last_updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjust_balance')
                    ->label('Adjust Balance')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->form([
                        Forms\Components\Select::make('operation')
                            ->options([
                                'add' => 'Add Amount',
                                'subtract' => 'Subtract Amount',
                                'set' => 'Set New Balance'
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->step(0.01)
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->placeholder('Reason for balance adjustment'),
                    ])
                    ->action(function ($record, array $data) {
                        $amount = (float) $data['amount'];

                        switch ($data['operation']) {
                            case 'add':
                                $record->current_balance += $amount;
                                break;
                            case 'subtract':
                                $record->current_balance -= $amount;
                                break;
                            case 'set':
                                $record->current_balance = $amount;
                                break;
                        }

                        $record->save();

                        // Create transaction record
                        SafeBalanceService::createBalanceTransaction(
                            $record->safe_type_id,
                            $data['operation'] === 'add' ? 'deposit' : 'withdrawal',
                            $amount,
                            $data['reason']
                        );
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('total_balance')
                    ->label(function () {
                        $totalBalance = SafeBalanceService::getTotalSafeBalance();
                        return 'Total: $' . number_format($totalBalance, 2);
                    })
                    ->color('success')
                    ->button()
                    ->action(function () {
                        // Can add modal or redirect to summary page
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSafeBalances::route('/'),
            'create' => Pages\CreateSafeBalance::route('/create'),
            'edit' => Pages\EditSafeBalance::route('/{record}/edit'),
        ];
    }
}
