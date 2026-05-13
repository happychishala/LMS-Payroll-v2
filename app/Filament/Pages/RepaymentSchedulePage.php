<?php
namespace App\Filament\Pages;

use App\Exports\RepaymentScheduleExport;
use App\Services\RepaymentScheduleService;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RepaymentSchedulePage extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Repayment Schedule Generator';
    protected static ?string $slug = 'repayment-schedule-page';
    protected static string $view = 'filament.pages.repayment-schedule-page';

    public ?string $loanType = null;
    public ?string $month = null;
    public array $data = [];
    public Collection $schedule;

    public function mount(): void
    {
        $this->schedule = collect();
        $this->data = [
            'loan_name' => null,
            'month' => null,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('loan_name')
                    ->label('Loan Type')
                    ->options([
                        'CNMC' => 'CNMC',
                        'GRZ' => 'GRZ',
                    ])
                    ->required(),

                DatePicker::make('month')
                    ->label('Month')
                    ->displayFormat('F Y')
                    ->required(),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $this->validate();

        $service = new RepaymentScheduleService();
        $this->schedule = $service->generateSchedule(
            $this->data['loan_name'] ?? null,
            $this->data['month'] ?? null
        );
    }

    public function exportToExcel(): BinaryFileResponse
    {
        $filename = 'Repayment_Schedule_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new RepaymentScheduleExport($this->schedule), $filename);
    }
}
