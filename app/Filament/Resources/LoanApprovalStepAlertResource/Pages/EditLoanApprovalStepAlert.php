<?php

namespace App\Filament\Resources\LoanApprovalStepAlertResource\Pages;

use App\Filament\Resources\LoanApprovalStepAlertResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoanApprovalStepAlert extends EditRecord
{
    protected static string $resource = LoanApprovalStepAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
