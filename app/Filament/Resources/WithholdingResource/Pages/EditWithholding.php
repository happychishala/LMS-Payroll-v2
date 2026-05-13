<?php

namespace App\Filament\Resources\WithholdingResource\Pages;

use App\Filament\Resources\WithholdingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWithholding extends EditRecord
{
    protected static string $resource = WithholdingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
