<?php

namespace App\Filament\Resources\RepaymentScheduleResource\Pages;

use App\Filament\Resources\RepaymentScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRepaymentSchedule extends EditRecord
{
    protected static string $resource = RepaymentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
