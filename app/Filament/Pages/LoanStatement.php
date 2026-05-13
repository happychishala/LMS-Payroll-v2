<?php
namespace App\Filament\Pages;

use App\Models\Loan;
use App\Services\LoanStatementService;
use Filament\Pages\Page;

class LoanStatement extends Page
{
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.loan-statement';
    protected static ?string $title = 'Loan Statement';

    public $loan;
    public string $loanId = '';
    public array $statement = [];

    public function mount(): void
    {
        $this->loanId = (string) (request('loan_id') ?? '');

        if ($this->loanId === '') {
            $this->loanId = (string) Loan::query()->orderBy('loan_id')->value('loan_id');
        }

        $this->statement = app(LoanStatementService::class)->build($this->loanId);
        $this->loan = $this->statement['loan'];
    }

    protected function getViewData(): array
    {
        return [
            'loan' => $this->loan,
            'loanId' => $this->loanId,
            'statement' => $this->statement,
        ];
    }
}
