<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DebtResource\Pages;
use App\Filament\Resources\DebtResource\RelationManagers;
use App\Models\Debt;
use App\Models\SafeType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DebtResource extends Resource
{
    protected static ?string $model = Debt::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Credit Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Debts & Credits';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Creditor Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('creditor_name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('creditor_phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),
                    ]),

                Forms\Components\Section::make('Debt Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('Total Debt Amount')
                                    ->numeric()
                                    ->step(0.01)
                                    ->required()
                                    ->prefix('$'),

                                Forms\Components\Select::make('safe_type_id')
                                    ->label('Associated Safe Type')
                                    ->options(SafeType::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('paid_amount')
                                    ->label('Already Paid')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0)
                                    ->prefix('$'),

                                Forms\Components\DatePicker::make('due_date')
                                    ->label('Due Date'),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'not_paid' => 'Not Paid',
                                        'partially_paid' => 'Partially Paid',
                                        'paid' => 'Fully Paid',
                                        'canceled' => 'Canceled',
                                    ])
                                    ->default('not_paid')
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Additional notes about this debt'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('debt_number')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creditor_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creditor_phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->money('USD')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('safeType.name')
                    ->label('Safe Type')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'not_paid',
                        'warning' => 'partially_paid',
                        'success' => 'paid',
                        'secondary' => 'canceled',
                    ]),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(function ($record) {
                        if ($record->due_date && $record->status !== 'paid') {
                            return $record->due_date->isPast() ? 'danger' : 'primary';
                        }
                        return 'primary';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'not_paid' => 'Not Paid',
                        'partially_paid' => 'Partially Paid',
                        'paid' => 'Fully Paid',
                        'canceled' => 'Canceled',
                    ]),

                Tables\Filters\SelectFilter::make('safe_type_id')
                    ->label('Safe Type')
                    ->options(SafeType::pluck('name', 'id')),

                Tables\Filters\Filter::make('overdue')
                    ->query(fn ($query) => $query->where('due_date', '<', now())
                        ->whereNotIn('status', ['paid', 'canceled']))
                    ->label('Overdue Debts'),
            ])
            ->actions([
                Tables\Actions\Action::make('make_payment')
                    ->label('Make Payment')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn ($record) => in_array($record->status, ['not_paid', 'partially_paid']))
                    ->form([
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->step(0.01)
                            ->required()
                            ->prefix('$')
                            ->helperText(fn ($record) => "Maximum payable amount: $" . number_format($record->remaining_amount, 2)),

                        Forms\Components\Select::make('safe_type_id')
                            ->label('Pay From Safe')
                            ->options(SafeType::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(fn ($record) => $record->safe_type_id),

                        Forms\Components\DatePicker::make('payment_date')
                            ->required()
                            ->default(now()),

                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->placeholder('Payment notes'),
                    ])
                    ->action(function ($record, array $data) {
                        \App\Models\DebtPayment::create([
                            'debt_id' => $record->id,
                            'safe_type_id' => $data['safe_type_id'],
                            'payment_amount' => $data['payment_amount'],
                            'payment_date' => $data['payment_date'],
                            'notes' => $data['notes'],
                        ]);
                    }),

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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDebts::route('/'),
            'create' => Pages\CreateDebt::route('/create'),
            'view' => Pages\ViewDebt::route('/{record}'),
            'edit' => Pages\EditDebt::route('/{record}/edit'),
        ];
    }
}
