<?php

namespace App\Filament\Resources;

use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\Toggle;
use App\Filament\Resources\ThirdPartyResource\Pages;
use App\Models\ThirdParty;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ThirdPartyResource extends Resource
{
    protected static ?string $model = ThirdParty::class;
    protected static ?string $navigationGroup = 'Addons';
    protected static ?string $navigationIcon = 'fas-plus';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Third Party Name')
                    ->prefixIcon('heroicon-o-user')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('base_uri')
                    ->prefix('https://')
                    ->suffixIcon('heroicon-m-globe-alt'),
                Forms\Components\TextInput::make('endpoint')
                    ->suffixIcon('heroicon-m-globe-alt')
                    ->prefix('/'),
                    Forms\Components\TextInput::make('token')
                    ->label('API/TOKEN')
                    ->prefixIcon('fas-lock'),
                    Toggle::make('is_active')
                    ->helperText('This third party will only be activated and start functioning when you switch on this.')
                    ->onColor('success')
                    ->offColor('danger')
                    ->default(false)
                    ->inline(false),
                    Forms\Components\TextInput::make('sender_id')
                    ->label('Sender ID')
                    ->minLength(2)
                    ->maxLength(12)
                    ->columnSpan(2),
                    

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('token')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('active_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
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
            'index' => Pages\ListThirdParties::route('/'),
            'create' => Pages\CreateThirdParty::route('/create'),
            'view' => Pages\ViewThirdParty::route('/{record}'),
            'edit' => Pages\EditThirdParty::route('/{record}/edit'),
        ];
    }
}
