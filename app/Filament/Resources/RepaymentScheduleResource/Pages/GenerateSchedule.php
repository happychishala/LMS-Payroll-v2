<?php
namespace App\Filament\Resources\RepaymentScheduleResource\Pages;

use App\Exports\RepaymentScheduleExport;
use App\Services\RepaymentScheduleService;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class GenerateSchedule extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = \App\Filament\Resources\RepaymentScheduleResource::class;

    public ?string $loanType = null;
    public ?string $month = null;
    public Collection $schedule;

    public function mount(): void
    {
        $this->schedule = collect();
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('loanType')
                ->label('Loan Type')
                ->options(['CNMC' => 'CNMC', 'GRZ' => 'GRZ'])
                ->required(),

            Forms\Components\DatePicker::make('month')
                ->label('Target Month')
                ->displayFormat('F Y')
                ->required(),
        ];
    }

    protected function getFormModel(): Model|string|null
    {
        return $this;
    }

    public function generateSchedule(): void
    {
        $service = new RepaymentScheduleService();

        try {
            $this->schedule = $service->generateSchedule($this->loanType, $this->month);

            Notification::make()
                ->title('Schedule Generated')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportToExcel(): BinaryFileResponse
    {
        $filename = 'Repayment_Schedule_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new RepaymentScheduleExport($this->schedule), $filename);
    }

    protected function getActions(): array
    {
        return [
            Action::make('Generate')
                ->action('generateSchedule'),
            Action::make('Export Excel')
                ->action('exportToExcel')
                ->visible(fn () => $this->schedule->isNotEmpty()),
        ];
    }

    protected static string $view = 'filament.resources.repayment-schedule-resource.pages.generate-schedule';
}
