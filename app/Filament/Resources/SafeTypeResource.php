<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SafeTypeResource\Pages;
use App\Filament\Resources\SafeTypeResource\RelationManagers;
use App\Models\SafeType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SafeTypeResource extends Resource
{
    protected static ?string $model = SafeType::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Safe Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Safe Types';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Bank ABC, PayPal Wallet'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'bank' => 'Bank',
                                'wallet' => 'Digital Wallet',
                                'cash' => 'Cash Safe',
                                'other' => 'Other'
                            ])
                            ->required()
                            ->default('bank'),
                    ]),

                Forms\Components\TextInput::make('account_number')
                    ->maxLength(255)
                    ->placeholder('Account number or wallet ID'),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->placeholder('Additional details about this safe type'),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'bank',
                        'success' => 'wallet',
                        'warning' => 'cash',
                        'secondary' => 'other',
                    ]),

                Tables\Columns\TextColumn::make('account_number')
                    ->searchable()
                    ->placeholder('Not specified'),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Current Balance')
                    ->money('USD')
                    ->getStateUsing(function ($record) {
                        return $record->safeBalance?->current_balance ?? 0;
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'bank' => 'Bank',
                        'wallet' => 'Digital Wallet',
                        'cash' => 'Cash Safe',
                        'other' => 'Other'
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSafeTypes::route('/'),
            'create' => Pages\CreateSafeType::route('/create'),
            'edit' => Pages\EditSafeType::route('/{record}/edit'),
        ];
    }
}
