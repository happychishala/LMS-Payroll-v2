<?php

namespace App\Filament\Pages;

use App\Models\Loan;
use App\Models\Repayments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Columns\TextColumn;

class LoanStatementPage extends Page implements HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    // ────────────────────────────────────────────────────────────────────
    //  Filament configuration
    // ────────────────────────────────────────────────────────────────────

    protected static string $view            = 'filament.resources.wallet-resource.pages.loan-statement';
    protected static ?string $title          = 'Loan Statement';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    // ────────────────────────────────────────────────────────────────────
    //  FORM STATE PROPERTIES (public so Filament can bind/validate them)
    // ────────────────────────────────────────────────────────────────────

    public ?int    $loan_id   = null;
    public ?string $from_date = null;
    public ?string $to_date   = null;

    // This is where we’ll stash the statement rows:
    public array   $statement = [];

    // ────────────────────────────────────────────────────────────────────
    //  PREVENT FILAMENT FROM TRYING TO READ/WRITE A MODEL
    // ────────────────────────────────────────────────────────────────────

    protected function getFormModel(): Model|string|null
    {
        // We’re not writing to any Eloquent here, so return null
        return null;
    }

    // ────────────────────────────────────────────────────────────────────
    //  DEFINE YOUR FORM FIELDS
    // ────────────────────────────────────────────────────────────────────

    protected function getFormSchema(): array
    {
        return [
            Select::make('loan_id')
                ->label('Loan')
                ->options(Loan::pluck('loan_number', 'id')->toArray())
                ->searchable()
                ->required(),

            DatePicker::make('from_date')
                ->label('From')
                ->required(),

            DatePicker::make('to_date')
                ->label('To')
                ->required(),

            Actions::make([
                Action::make('generate')
                    ->label('Show Statement')
                    ->action('generateStatement'),
            ]),
        ];
    }

    //
    // ─── THIS RUNS WHEN YOU CLICK “Show Statement” ───────────────────────────────
    //
    public function generateStatement(): void
    {
        // nothing to store here — the table will re-query using our props
        // we just need to re-render the page
    }

    //
    // ─── TABLE: we only override getTableQuery() and getTableColumns() ──────────
    //
    // Filament will call getTableQuery() whenever it renders the table.
    public function getTableQuery(): Builder
    {
        return Repayments::query()
            ->select([
                'repayment_date as date',
                'payments',
                'balance as balance',
            ])
            ->where('loan_id', $this->loan_id)
            ->whereBetween('repayment_date', [$this->from_date, $this->to_date])
            ->orderBy('repayment_date');
    }

    public function getTableColumns(): array
    {
        return [
            TextColumn::make('date')->label('Date')->date(),
            TextColumn::make('payments')->label('Amount')->money('ZMW'),
            TextColumn::make('balance')->label('Balance')->money('ZMW'),
        ];
    }
    public function getTableRecordKey($record): string
    {
        // must return a string; combine date+amount so each row is unique
        return (string) data_get($record, 'date') . '|' . data_get($record, 'amount');
    }
}
