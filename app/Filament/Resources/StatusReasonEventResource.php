<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatusReasonEventResource\Pages;
use App\Models\StatusReasonEvent;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StatusReasonEventResource extends Resource
{
    protected static ?string $model = StatusReasonEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Loans';

    protected static ?string $navigationLabel = 'Status Reason Audit';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('loan_id')
                    ->badge()
                    ->searchable(),
                TextColumn::make('client_id')
                    ->label('Client ID')
                    ->searchable(),
                TextColumn::make('previousStatusReason.code')
                    ->label('Previous'),
                TextColumn::make('statusReason.code')
                    ->label('New'),
                TextColumn::make('action')
                    ->badge(),
                TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('performed_by_name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('mode_of_exit')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('affordability_reason')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('removal_reason')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'assigned' => 'Assigned',
                        'changed' => 'Changed',
                        'removed' => 'Removed',
                    ]),
                SelectFilter::make('status_reason_id')
                    ->relationship('statusReason', 'code')
                    ->label('New Code'),
                Filter::make('today')
                    ->query(fn ($query) => $query->whereDate('created_at', now()->toDateString())),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatusReasonEvents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
