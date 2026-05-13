<?php

namespace App\Filament\Resources\WithholdingResource\Pages;

use App\Filament\Resources\WithholdingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWithholdings extends ListRecords
{
    protected static string $resource = WithholdingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
