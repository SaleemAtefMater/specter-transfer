<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DebtPaymentResource\Pages;
use App\Filament\Resources\DebtPaymentResource\RelationManagers;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\SafeType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DebtPaymentResource extends Resource
{
    protected static ?string $model = DebtPayment::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Credit Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Debt Payments';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('debt_id')
                    ->label('Select Debt')
                    ->options(function () {
                        return Debt::whereIn('status', ['not_paid', 'partially_paid'])
                            ->get()
                            ->mapWithKeys(function ($debt) {
                                return [$debt->id => "{$debt->debt_number} - {$debt->creditor_name} (Remaining: $" . number_format($debt->remaining_amount, 2) . ")"];
                            });
                    })
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        if ($state) {
                            $debt = Debt::find($state);
                            if ($debt) {
                                $set('safe_type_id', $debt->safe_type_id);
                            }
                        }
                    }),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->step(0.01)
                            ->required()
                            ->prefix('$')
                            ->helperText(function (Forms\Get $get) {
                                if ($get('debt_id')) {
                                    $debt = Debt::find($get('debt_id'));
                                    return $debt ? "Maximum payable amount: $" . number_format($debt->remaining_amount, 2) : '';
                                }
                                return '';
                            }),

                        Forms\Components\Select::make('safe_type_id')
                            ->label('Pay From Safe')
                            ->options(SafeType::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ]),

                Forms\Components\DatePicker::make('payment_date')
                    ->required()
                    ->default(now()),

                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->placeholder('Payment notes or reference'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('debt.debt_number')
                    ->label('Debt Number')
                    ->searchable(),

                Tables\Columns\TextColumn::make('debt.creditor_name')
                    ->label('Creditor')
                    ->searchable(),

                Tables\Columns\TextColumn::make('payment_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('safeType.name')
                    ->label('Paid From')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('debt.remaining_amount')
                    ->label('Debt Remaining')
                    ->money('USD')
                    ->color(fn($state) => $state > 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('safe_type_id')
                    ->label('Paid From Safe')
                    ->options(SafeType::pluck('name', 'id')),

                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        Forms\Components\DatePicker::make('payment_from'),
                        Forms\Components\DatePicker::make('payment_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['payment_from'], fn($q, $date) => $q->whereDate('payment_date', '>=', $date))
                            ->when($data['payment_until'], fn($q, $date) => $q->whereDate('payment_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        // Reverse the payment effects
                        $debt = $record->debt;
                        $debt->paid_amount -= $record->payment_amount;
                        $debt->save();

                        // Add back to safe balance
                        $safeBalance = $record->safeType->safeBalance;
                        if ($safeBalance) {
                            $safeBalance->current_balance += $record->payment_amount;
                            $safeBalance->save();
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDebtPayments::route('/'),
            'create' => Pages\CreateDebtPayment::route('/create'),
            'view' => Pages\ViewDebtPayment::route('/{record}'),
            'edit' => Pages\EditDebtPayment::route('/{record}/edit'),
        ];
    }
}
