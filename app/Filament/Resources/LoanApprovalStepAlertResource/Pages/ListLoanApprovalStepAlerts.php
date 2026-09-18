<?php

namespace App\Filament\Resources\LoanApprovalStepAlertResource\Pages;

use App\Filament\Resources\LoanApprovalStepAlertResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoanApprovalStepAlerts extends ListRecords
{
    protected static string $resource = LoanApprovalStepAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
