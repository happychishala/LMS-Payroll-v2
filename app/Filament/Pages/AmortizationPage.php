<?php

namespace App\Filament\Pages;

use App\Models\Loan;
use App\Services\AmortizationService;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmortizationPage extends Page
{
    use InteractsWithForms;

    protected static string $view            = 'filament.resources.wallet-resource.pages.amortization';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup= 'Tools';
    protected static ?string $title          = 'Loan Amortization';

    //
    // ─── PUBLIC PROPS FOR FORM BINDING ────────────────────────────────────────────
    //
    public ?int    $loan_id       = null;
    public string  $borrower_name = '';
    public string  $loan_number   = '';
    public float   $principal     = 0;
    public float   $rate          = 0;
    public int     $term          = 0;         // in months
    public string  $start_date    = '';
    public float   $extra_payment = 0;

    //
    // ─── WHERE WE STASH THE SCHEDULE ─────────────────────────────────────────────
    //
    public array $schedule = [];

    //
    // ─── OVERRIDE getForm TO BIND PROPS ──────────────────────────────────────────
    //
    public function getForm(string $name): ?Form
    {
        $form = parent::getForm($name);

        if ($form) {
            // Bind this Page class’s public props to the form
          
        }

        return $form;
    }

    //
    // ─── DEFINE THE FORM FIELDS ──────────────────────────────────────────────────
    //
    protected function getFormSchema(): array
    {
        return [
            Select::make('loan_id')
                ->label('Loan')
                ->options(Loan::pluck('loan_number', 'id')->toArray())
                ->searchable()
                ->reactive()
                ->afterStateUpdated(function (int $state, callable $set) {
                    $loan = Loan::with('borrower','loanType')->find($state);
                    if (! $loan) {
                        return;
                    }

                    // auto‐fill borrower & loan #
                    $set('borrower_name', $loan->borrower->full_name);
                    $set('loan_number',   $loan->loan_number);

                    // auto‐fill principal & rate from loanType
                    $set('principal',     $loan->principal_amount);
                    $set('rate',          $loan->loanType->interest_rate);
                })
                ->required(),

            TextInput::make('borrower_name')
                ->label('Borrower')
                ->disabled(),

            TextInput::make('loan_number')
                ->label('Loan #')
                ->disabled(),

            TextInput::make('principal')
                ->label('Loan Amount (ZMW)')
                ->numeric()
                ->required(),

            TextInput::make('rate')
                ->label('Annual Rate (%)')
                ->numeric()
                ->required(),

            TextInput::make('term')
                ->label('Term (Months)')
                ->numeric()
                ->required(),

            DatePicker::make('start_date')
                ->label('First Payment Date')
                ->required(),

            TextInput::make('extra_payment')
                ->label('Optional Extra Payment')
                ->numeric(),

            FormActions::make([
                Action::make('calculate')
                    ->label('Calculate Schedule')
                    ->action('calculateSchedule')
                    ->button(),

                Action::make('export')
                    ->label('Export CSV')
                    ->action('exportCsv')
                    ->button(),
            ]),
        ];
    }

    //
    // ─── CALC & EXPORT HANDLERS ───────────────────────────────────────────────────
    //
    public function calculateSchedule(): void
    {
        $this->schedule = app(AmortizationService::class)
            ->generateSchedule(
                $this->principal,
                $this->rate,
                $this->term,
                $this->start_date,
                $this->extra_payment,
            );
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'amortization_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(
            function () {
                $handle = fopen('php://output', 'w');
                // header row
                fputcsv($handle, ['Period','Date','Payment','Interest','Principal','Balance']);
                // data rows
                foreach ($this->schedule as $row) {
                    fputcsv($handle, [
                        $row['period'],
                        $row['date'],
                        number_format($row['payment'],   2, '.', ''),
                        number_format($row['interest'],  2, '.', ''),
                        number_format($row['principal'], 2, '.', ''),
                        number_format($row['balance'],   2, '.', ''),
                    ]);
                }
                fclose($handle);
            },
            $filename
        );
    }
}
