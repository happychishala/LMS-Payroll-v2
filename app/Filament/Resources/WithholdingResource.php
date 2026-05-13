<?php
namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use App\Models\Withholding;
use App\Filament\Resources\WithholdingResource\Pages;

class WithholdingResource extends Resource
{
    protected static ?string $model = Withholding::class;
    protected static ?string $navigationGroup = 'Repayments';
    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';

    public static function form(Forms\Form $form): Forms\Form
    {
        $isEdit = request()->routeIs('filament.resources.withholdings.edit');

        return $form->schema([
            $isEdit
                ? Forms\Components\TextInput::make('loan_id')
                    ->label('Loan')
                    ->disabled()
                : Forms\Components\Select::make('loan_id')
                    ->relationship('loan', 'loan_id')
                    ->required(),

            $isEdit
                ? Forms\Components\TextInput::make('borrower_id')
                    ->label('Borrower')
                    ->disabled()
                : Forms\Components\Select::make('borrower_id')
                    ->relationship('borrower', 'customer_id')
                    ->required(),

            Forms\Components\DatePicker::make('installment_due_date')->required(),
            Forms\Components\TextInput::make('installment_amount')->numeric()->required(),
            Forms\Components\Select::make('withholding_status')
                ->options([
                    'Pending' => 'Pending',
                    'Refund' => 'Refund',
                    'Withheld' => 'Withheld',
                    'Refunded' => 'Refunded',
                ])->required(),
            Forms\Components\Textarea::make('remarks')->columnSpanFull(),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('loan_id'),
            Tables\Columns\TextColumn::make('borrower_id'),
            Tables\Columns\TextColumn::make('installment_due_date')->date(),
            Tables\Columns\TextColumn::make('installment_amount')->money('ZMW'),
            Tables\Columns\BadgeColumn::make('withholding_status')
                ->colors([
                    'secondary' => 'Pending',
                    'warning' => 'Refund',
                    'danger' => 'Withheld',
                    'success' => 'Refunded',
                ]),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('withholding_status')->options([
                'Pending' => 'Pending',
                'Refund' => 'Refund',
                'Withheld' => 'Withheld',
                'Refunded' => 'Refunded',
            ]),
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
            'index' => Pages\ListWithholdings::route('/'),
            'create' => Pages\CreateWithholding::route('/create'),
            'edit' => Pages\EditWithholding::route('/{record}/edit'),
        ];
    }
}
