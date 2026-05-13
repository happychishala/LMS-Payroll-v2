<?php

namespace App\Filament\Resources\RepaymentScheduleResource\Pages;

use App\Filament\Resources\RepaymentScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRepaymentSchedules extends ListRecords
{
    protected static string $resource = RepaymentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
