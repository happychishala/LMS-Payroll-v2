<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatusReasonResource\Pages;
use App\Models\StatusReason;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StatusReasonResource extends Resource
{
    protected static ?string $model = StatusReason::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Loans';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Definition')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10),
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255),
                        Select::make('series')
                            ->options([
                                'A-Series' => 'A-Series',
                                'D-Series' => 'D-Series',
                                'I-Series' => 'I-Series',
                                'W-Series' => 'W-Series',
                            ])
                            ->required(),
                        TextInput::make('group')
                            ->label('Category')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('assignment_condition')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Flags')
                    ->columns(3)
                    ->schema([
                        Toggle::make('suspend_submissions'),
                        Toggle::make('client_may_replace'),
                        Toggle::make('suspend_interest'),
                        Toggle::make('requires_management_approval'),
                        Toggle::make('triggers_insurance_claim'),
                        Toggle::make('blocks_new_loan'),
                        Toggle::make('allows_manager_override'),
                        Toggle::make('requires_mode_of_exit'),
                        Toggle::make('requires_affordability_reason'),
                        Toggle::make('is_active'),
                    ]),
                Section::make('Structured Input')
                    ->schema([
                        TagsInput::make('input_options.mode_of_exit')
                            ->label('Mode Of Exit Options')
                            ->placeholder('Add option')
                            ->visible(fn ($get) => (bool) $get('requires_mode_of_exit')),
                        TagsInput::make('input_options.affordability_reason')
                            ->label('Affordability Reason Options')
                            ->placeholder('Add option')
                            ->visible(fn ($get) => (bool) $get('requires_affordability_reason')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('series')
                    ->sortable(),
                TextColumn::make('group')
                    ->label('Category')
                    ->sortable(),
                IconColumn::make('suspend_submissions')
                    ->boolean(),
                IconColumn::make('client_may_replace')
                    ->boolean(),
                IconColumn::make('suspend_interest')
                    ->boolean(),
                IconColumn::make('blocks_new_loan')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('series')
                    ->options([
                        'A-Series' => 'A-Series',
                        'D-Series' => 'D-Series',
                        'I-Series' => 'I-Series',
                        'W-Series' => 'W-Series',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatusReasons::route('/'),
            'create' => Pages\CreateStatusReason::route('/create'),
            'edit' => Pages\EditStatusReason::route('/{record}/edit'),
        ];
    }
}
