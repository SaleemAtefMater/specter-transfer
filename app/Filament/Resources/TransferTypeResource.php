<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransferTypeResource\Pages;
use App\Filament\Resources\TransferTypeResource\RelationManagers;
use App\Models\TransferType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransferTypeResource extends Resource
{
    protected static ?string $model = TransferType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
           return $form
               ->schema([
                   Forms\Components\TextInput::make('name')
                       ->required()
                       ->maxLength(255),
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
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('transfers_count')
                    ->counts('transfers')
                    ->label('Transfers'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransferTypes::route('/'),
            'create' => Pages\CreateTransferType::route('/create'),
            'view' => Pages\ViewTransferType::route('/{record}'),
            'edit' => Pages\EditTransferType::route('/{record}/edit'),
        ];
    }
}
