<?php

namespace App\Filament\Resources\StatusReasonResource\Pages;

use App\Filament\Resources\StatusReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStatusReasons extends ListRecords
{
    protected static string $resource = StatusReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
