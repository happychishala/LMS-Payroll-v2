<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanApprovalStepAlertResource\Pages;
use App\Models\LoanApprovalStepAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class LoanApprovalStepAlertResource extends Resource
{
    protected static ?string $model = LoanApprovalStepAlert::class;

    protected static ?string $navigationGroup = 'Loans';

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Approval Alerts';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->label('Loan Step / Status')
                ->options([
                    'requested' => 'Requested',
                    'processing' => 'Processing',
                    'approved' => 'Approved',
                    'denied' => 'Denied',
                    'defaulted' => 'Defaulted',
                    'settled' => 'Closed',
                    'paid off' => 'Paid Off',
                ])
                ->searchable()
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('label')
                ->required()
                ->maxLength(125),
            Forms\Components\TagsInput::make('role_names')
                ->label('Responsible Roles')
                ->suggestions(fn (): array => Role::query()->orderBy('name')->pluck('name')->all())
                ->placeholder('Add role name'),
            Forms\Components\TagsInput::make('emails')
                ->label('Additional Emails')
                ->placeholder('Add email address'),
            Forms\Components\Toggle::make('is_active')
                ->label('Send Alerts')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->label('Step')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role_names')
                    ->label('Roles')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->wrap(),
                Tables\Columns\TextColumn::make('emails')
                    ->label('Emails')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoanApprovalStepAlerts::route('/'),
            'create' => Pages\CreateLoanApprovalStepAlert::route('/create'),
            'edit' => Pages\EditLoanApprovalStepAlert::route('/{record}/edit'),
        ];
    }
}
